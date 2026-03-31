<?php
/**
 *
 * This file is part of HESK - PHP Help Desk Software.
 *
 * (c) Copyright Klemen Stirn. All rights reserved.
 * https://www.hesk.com
 *
 * For the full copyright and license agreement information visit
 * https://www.hesk.com/eula.php
 *
 */

/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {die('Invalid attempt');}

function hesk_aiAutoAssignTicketFields(&$ticket, $options = array())
{
    global $hesk_settings;

    $request_id = function_exists('random_bytes') ? bin2hex(random_bytes(6)) : substr(md5(uniqid('', true)), 0, 12);

    $result = array(
        'applied' => false,
        'category_applied' => false,
        'priority_applied' => false,
        'reason' => 'not_enabled',
    );

    if (empty($hesk_settings['ai_auto_assign_enabled'])) {
        hesk_aiLog('skipped', array('request_id' => $request_id, 'reason' => 'not_enabled'));
        return $result;
    }

    $client_config = hesk_aiGetClientConfig();
    if (!$client_config['ok']) {
        $result['reason'] = $client_config['reason'];
        hesk_aiLog('skipped', array('request_id' => $request_id, 'reason' => $result['reason']));
        return $result;
    }

    $subject = isset($options['subject']) ? $options['subject'] : (isset($ticket['subject']) ? $ticket['subject'] : '');
    $message = isset($options['message']) ? $options['message'] : (isset($ticket['message']) ? $ticket['message'] : '');

    $subject = trim(strip_tags((string) $subject));
    $message = trim(strip_tags((string) $message));

    if ($subject === '' && $message === '') {
        $result['reason'] = 'missing_text';
        hesk_aiLog('skipped', array('request_id' => $request_id, 'reason' => 'missing_text'));
        return $result;
    }

    if (hesk_mb_strlen($message) > 2500) {
        $message = hesk_mb_substr($message, 0, 2500);
    }

    $allowed_category_ids = isset($options['allowed_category_ids']) && is_array($options['allowed_category_ids'])
        ? array_values(array_unique(array_map('intval', $options['allowed_category_ids'])))
        : array();
    $category_type = isset($options['category_type']) ? intval($options['category_type']) : -1;

    $categories = hesk_aiGetCategoriesForAssignment($allowed_category_ids, $category_type);
    if (!count($categories)) {
        $result['reason'] = 'no_categories';
        hesk_aiLog('skipped', array('request_id' => $request_id, 'reason' => 'no_categories'));
        return $result;
    }

    $priorities = hesk_aiGetPrioritiesForAssignment();
    if (!count($priorities)) {
        $result['reason'] = 'no_priorities';
        hesk_aiLog('skipped', array('request_id' => $request_id, 'reason' => 'no_priorities'));
        return $result;
    }

    $min_confidence = isset($hesk_settings['ai_auto_assign_min_confidence'])
        ? floatval($hesk_settings['ai_auto_assign_min_confidence'])
        : 0.60;

    $allow_category_override = isset($options['allow_category_override'])
        ? (bool) $options['allow_category_override']
        : !empty($hesk_settings['ai_auto_assign_override_category']);

    $allow_priority_override = isset($options['allow_priority_override'])
        ? (bool) $options['allow_priority_override']
        : !empty($hesk_settings['ai_auto_assign_override_priority']);

    $system_prompt = '
    <system>
    <role>
        You are an AI classifier for a university helpdesk system.
        Your task is to assign the most accurate category_id and priority_id to each ticket.
    </role>

    <input_language>
        Requests may be written in Ukrainian or English.
        You MUST correctly interpret Ukrainian language inputs.
        If mixed language is used, prioritize semantic meaning over wording.
    </input_language>

    <output_format>
        You MUST return ONLY a valid JSON object with:
        {
            "category_id": integer,
            "priority_id": integer,
            "confidence": number (0..1),
            "reason": string (max 20 words)
        }
        No extra text. No explanations outside JSON.
    </output_format>

    <hard_constraints>
        - Use ONLY provided category_id values
        - Use ONLY provided priority_id values
        - NEVER invent IDs
        - If uncertain, choose best match and lower confidence
    </hard_constraints>

    <meta_thinking>
        Internally (DO NOT output), follow this process:
        1. Extract intent (what user actually wants)
        2. Detect urgency signals
        3. Detect impact scope (individual vs many users vs system-wide)
        4. Match best category based on routing intent
        5. Assign priority using rules below
        6. Validate consistency before output
    </meta_thinking>

    <priority_definitions>
        <critical>
            System-wide failure or blocking issue.
            Examples:
            - Entire platform down
            - Cannot access account AND urgent deadline
            - Payment system failure
            - Security issue
        </critical>

        <high>
            Major functionality broken for user(s).
            Examples:
            - Cannot submit assignment
            - Cannot log in (non-system-wide)
            - Key feature not working
        </high>

        <medium>
            Partial issue or inconvenience.
            Examples:
            - Feature works incorrectly
            - Performance issues
            - UI bugs affecting usability
        </medium>

        <low>
            Minor issue or request.
            Examples:
            - General questions
            - Feature requests
            - Cosmetic/UI issues
        </low>
    </priority_definitions>

    <priority_rules>
        - If deadlines + blocking issue → escalate to HIGH or CRITICAL
        - If many users affected → increase priority by 1 level
        - If purely informational → LOW
        - If unclear urgency → default to MEDIUM
    </priority_rules>

    <category_selection_rules>
        - Categories include natural language descriptions → use them
        - Match based on user intent, NOT keywords only
        - Prefer more specific category over generic
        - If multiple match → choose best routing destination
    </category_selection_rules>

    <confidence_rules>
        - 0.9–1.0 → clear intent, strong match
        - 0.7–0.89 → good match, minor ambiguity
        - 0.5–0.69 → uncertain classification
        - <0.5 → weak match (avoid if possible)
    </confidence_rules>

    <examples>
        <example>
            Input: "Не можу зайти в систему перед дедлайном"
            Output:
            {
                "priority_id": HIGH,
                "confidence": 0.9,
                "reason": "Login blocked before deadline"
            }
        </example>

        <example>
            Input: "Було б класно додати темну тему"
            Output:
            {
                "priority_id": LOW,
                "confidence": 0.95,
                "reason": "Feature request"
            }
        </example>

        <example>
            Input: "Система не працює у всіх студентів"
            Output:
            {
                "priority_id": CRITICAL,
                "confidence": 0.95,
                "reason": "System-wide outage"
            }
        </example>
    </examples>

    <final_check>
        Before responding:
        - Ensure valid JSON
        - Ensure IDs exist in provided options
        - Ensure priority matches rules
    </final_check>
</system>
    ';

    $user_payload = array(
        'subject' => $subject,
        'message' => $message,
        'category_options' => $categories,
        'priority_options' => $priorities,
        'current_category_id' => isset($ticket['category']) ? intval($ticket['category']) : 0,
        'current_priority_id' => isset($ticket['priority']) ? intval($ticket['priority']) : -1,
    );

    $request = hesk_aiExecuteJsonPredictionRequest($client_config, $request_id, $system_prompt, $user_payload, array(
        'stage' => 'field_assignment',
        'min_confidence' => $min_confidence,
        'category_count' => count($categories),
        'priority_count' => count($priorities),
        'current_category_id' => isset($ticket['category']) ? intval($ticket['category']) : 0,
        'current_priority_id' => isset($ticket['priority']) ? intval($ticket['priority']) : -1,
        'subject_length' => hesk_mb_strlen($subject),
        'message_length' => hesk_mb_strlen($message),
        'subject_preview' => hesk_aiPreview($subject, 150),
        'message_preview' => hesk_aiPreview($message, 200),
    ));

    if (!$request['ok']) {
        $result['reason'] = $request['reason'];
        return $result;
    }

    $prediction = $request['prediction'];

    $predicted_category = isset($prediction['category_id']) ? intval($prediction['category_id']) : 0;
    $predicted_priority = isset($prediction['priority_id']) ? intval($prediction['priority_id']) : -1;
    $confidence = isset($prediction['confidence']) ? floatval($prediction['confidence']) : 0;

    if ($confidence < $min_confidence) {
        $result['reason'] = 'low_confidence';
        $result['confidence'] = $confidence;
        hesk_aiLog('skipped', array(
            'request_id' => $request_id,
            'reason' => 'low_confidence',
            'confidence' => $confidence,
            'predicted_category' => $predicted_category,
            'predicted_priority' => $predicted_priority,
        ));
        return $result;
    }

    $valid_category_ids = array();
    foreach ($categories as $category) {
        $valid_category_ids[] = intval($category['id']);
    }

    $valid_priority_ids = array();
    foreach ($priorities as $priority) {
        $valid_priority_ids[] = intval($priority['id']);
    }

    if ($predicted_category && in_array($predicted_category, $valid_category_ids, true)) {
        if ($allow_category_override || empty($ticket['category'])) {
            $ticket['category'] = $predicted_category;
            $result['category_applied'] = true;
        }
    }

    if (in_array($predicted_priority, $valid_priority_ids, true)) {
        if ($allow_priority_override || !isset($ticket['priority']) || intval($ticket['priority']) < 0) {
            $ticket['priority'] = $predicted_priority;
            $result['priority_applied'] = true;
        }
    }

    $result['applied'] = $result['category_applied'] || $result['priority_applied'];
    $result['reason'] = $result['applied'] ? 'applied' : 'no_changes';
    $result['confidence'] = $confidence;

    if (!empty($hesk_settings['ai_auto_assign_debug'])) {
        $result['prediction'] = $prediction;
    }

    hesk_aiLog('completed', array(
        'request_id' => $request_id,
        'reason' => $result['reason'],
        'applied' => $result['applied'],
        'category_applied' => $result['category_applied'],
        'priority_applied' => $result['priority_applied'],
        'category' => isset($ticket['category']) ? intval($ticket['category']) : 0,
        'priority' => isset($ticket['priority']) ? intval($ticket['priority']) : -1,
        'confidence' => $confidence,
        'prediction' => $prediction,
    ));

    return $result;
}

function hesk_aiAutoAssignTicketOwner(&$ticket, $options = array())
{
    global $hesk_settings;

    $request_id = function_exists('random_bytes') ? bin2hex(random_bytes(6)) : substr(md5(uniqid('', true)), 0, 12);

    $result = array(
        'applied' => false,
        'reason' => 'not_enabled',
    );

    if (empty($hesk_settings['ai_auto_assign_enabled']) || empty($hesk_settings['ai_auto_assign_owner_enabled'])) {
        $result['reason'] = empty($hesk_settings['ai_auto_assign_enabled']) ? 'not_enabled' : 'owner_not_enabled';
        hesk_aiLog('skipped', array('request_id' => $request_id, 'stage' => 'owner_assignment', 'reason' => $result['reason']));
        return $result;
    }

    $client_config = hesk_aiGetClientConfig();
    if (!$client_config['ok']) {
        $result['reason'] = $client_config['reason'];
        hesk_aiLog('skipped', array('request_id' => $request_id, 'stage' => 'owner_assignment', 'reason' => $result['reason']));
        return $result;
    }

    $subject = isset($options['subject']) ? $options['subject'] : (isset($ticket['subject']) ? $ticket['subject'] : '');
    $message = isset($options['message']) ? $options['message'] : (isset($ticket['message']) ? $ticket['message'] : '');

    $subject = trim(strip_tags((string) $subject));
    $message = trim(strip_tags((string) $message));

    if ($subject === '' && $message === '') {
        $result['reason'] = 'missing_text';
        hesk_aiLog('skipped', array('request_id' => $request_id, 'stage' => 'owner_assignment', 'reason' => 'missing_text'));
        return $result;
    }

    if (hesk_mb_strlen($message) > 2500) {
        $message = hesk_mb_substr($message, 0, 2500);
    }

    $category_id = isset($options['category_id']) ? intval($options['category_id']) : (isset($ticket['category']) ? intval($ticket['category']) : 0);
    if ($category_id < 1) {
        $result['reason'] = 'missing_category';
        hesk_aiLog('skipped', array('request_id' => $request_id, 'stage' => 'owner_assignment', 'reason' => 'missing_category'));
        return $result;
    }

    $allow_owner_override = isset($options['allow_owner_override'])
        ? (bool) $options['allow_owner_override']
        : !empty($hesk_settings['ai_auto_assign_override_owner']);

    $current_owner_id = isset($ticket['owner']) ? intval($ticket['owner']) : 0;
    if ($current_owner_id > 0 && !$allow_owner_override) {
        $result['reason'] = 'owner_exists_no_override';
        hesk_aiLog('skipped', array(
            'request_id' => $request_id,
            'stage' => 'owner_assignment',
            'reason' => 'owner_exists_no_override',
            'current_owner_id' => $current_owner_id,
        ));
        return $result;
    }

    $eligible_users = hesk_getAutoAssignEligibleUsers($category_id);
    if (!count($eligible_users)) {
        $result['reason'] = 'no_users';
        hesk_aiLog('skipped', array(
            'request_id' => $request_id,
            'stage' => 'owner_assignment',
            'reason' => 'no_users',
            'category_id' => $category_id,
        ));
        return $result;
    }

    $owner_options = hesk_aiGetUsersForOwnerAssignment($category_id, $eligible_users);
    $owner_map = array();
    foreach ($eligible_users as $eligible_user) {
        $owner_map[intval($eligible_user['id'])] = $eligible_user;
    }

    $min_confidence = isset($hesk_settings['ai_auto_assign_owner_min_confidence'])
        ? floatval($hesk_settings['ai_auto_assign_owner_min_confidence'])
        : 0.85;
    if ($min_confidence < 0) {
        $min_confidence = 0;
    } elseif ($min_confidence > 1) {
        $min_confidence = 1;
    }

    $category_info = array('id' => $category_id, 'name' => '', 'description' => '');
    $category_options = hesk_aiGetCategoriesForAssignment(array($category_id), -1);
    if (isset($category_options[0]) && is_array($category_options[0])) {
        $category_info = $category_options[0];
    }

    $system_prompt = '
    <system>
    <role>
        You are an AI helpdesk router that selects the best owner_id for a ticket after category selection is already complete.
    </role>

    <output_format>
        You MUST return ONLY a valid JSON object with:
        {
            "owner_id": integer,
            "confidence": number (0..1),
            "reason": string (max 20 words)
        }
        No extra text. No explanations outside JSON.
    </output_format>

    <hard_constraints>
        - Use ONLY provided owner_id values
        - NEVER invent IDs
        - Use the resolved category as the primary routing context
        - If no staff member is a strong fit, return the closest owner_id with lower confidence
    </hard_constraints>

    <matching_rules>
        - Staff options include natural language descriptions of what they handle best
        - Match based on ownership fit, not keyword overlap alone
        - Prefer specialists whose description clearly matches the ticket intent
        - Lower confidence when multiple staff members seem similarly suitable
        - Lower confidence when the ticket could be handled by anyone in the category
    </matching_rules>

    <confidence_rules>
        - 0.9–1.0 → clear staff fit, specific ownership match
        - 0.8–0.89 → good fit, minor ambiguity
        - 0.6–0.79 → weak or shared fit
        - <0.6 → poor match
    </confidence_rules>

    <final_check>
        Before responding:
        - Ensure valid JSON
        - Ensure owner_id exists in provided options
        - Ensure confidence reflects match strength
    </final_check>
</system>
    ';

    $user_payload = array(
        'subject' => $subject,
        'message' => $message,
        'resolved_category' => $category_info,
        'owner_options' => $owner_options,
        'current_owner_id' => $current_owner_id,
    );

    $request = hesk_aiExecuteJsonPredictionRequest($client_config, $request_id, $system_prompt, $user_payload, array(
        'stage' => 'owner_assignment',
        'min_confidence' => $min_confidence,
        'category_id' => $category_id,
        'current_owner_id' => $current_owner_id,
        'owner_count' => count($owner_options),
        'subject_length' => hesk_mb_strlen($subject),
        'message_length' => hesk_mb_strlen($message),
        'subject_preview' => hesk_aiPreview($subject, 150),
        'message_preview' => hesk_aiPreview($message, 200),
    ));

    if (!$request['ok']) {
        $result['reason'] = $request['reason'];
        return $result;
    }

    $prediction = $request['prediction'];
    $predicted_owner_id = isset($prediction['owner_id']) ? intval($prediction['owner_id']) : 0;
    $confidence = isset($prediction['confidence']) ? floatval($prediction['confidence']) : 0;

    if ($confidence < $min_confidence) {
        $result['reason'] = 'low_confidence';
        $result['confidence'] = $confidence;
        hesk_aiLog('skipped', array(
            'request_id' => $request_id,
            'stage' => 'owner_assignment',
            'reason' => 'low_confidence',
            'confidence' => $confidence,
            'predicted_owner_id' => $predicted_owner_id,
            'category_id' => $category_id,
        ));
        return $result;
    }

    if ($predicted_owner_id > 0 && isset($owner_map[$predicted_owner_id])) {
        $ticket['owner'] = $predicted_owner_id;
        $result['applied'] = true;
        $result['owner_id'] = $predicted_owner_id;
        $result['owner'] = $owner_map[$predicted_owner_id];
        $hesk_settings['user_data'][$predicted_owner_id] = $owner_map[$predicted_owner_id];
    }

    $result['reason'] = $result['applied'] ? 'applied' : 'no_changes';
    $result['confidence'] = $confidence;

    if (!empty($hesk_settings['ai_auto_assign_debug'])) {
        $result['prediction'] = $prediction;
    }

    hesk_aiLog('completed', array(
        'request_id' => $request_id,
        'stage' => 'owner_assignment',
        'reason' => $result['reason'],
        'applied' => $result['applied'],
        'owner_id' => isset($result['owner_id']) ? intval($result['owner_id']) : 0,
        'category_id' => $category_id,
        'confidence' => $confidence,
        'prediction' => $prediction,
    ));

    return $result;
}

function hesk_aiGetClientConfig()
{
    global $hesk_settings;

    $api_key = isset($hesk_settings['ai_auto_assign_api_key']) ? trim($hesk_settings['ai_auto_assign_api_key']) : '';
    if ($api_key === '') {
        return array('ok' => false, 'reason' => 'missing_api_key');
    }

    if (!function_exists('curl_init')) {
        return array('ok' => false, 'reason' => 'curl_missing');
    }

    $model = isset($hesk_settings['ai_auto_assign_model']) && trim($hesk_settings['ai_auto_assign_model']) !== ''
        ? trim($hesk_settings['ai_auto_assign_model'])
        : 'gpt-4o-mini';

    $timeout = isset($hesk_settings['ai_auto_assign_timeout']) ? intval($hesk_settings['ai_auto_assign_timeout']) : 10;
    if ($timeout < 5) {
        $timeout = 10;
    }

    return array(
        'ok' => true,
        'api_key' => $api_key,
        'model' => $model,
        'timeout' => $timeout,
    );
}

function hesk_aiExecuteJsonPredictionRequest($client_config, $request_id, $system_prompt, $user_payload, $log_context = array())
{
    $request_context = array_merge($log_context, array(
        'request_id' => $request_id,
        'model' => $client_config['model'],
        'timeout' => $client_config['timeout'],
    ));

    hesk_aiLog('request_started', $request_context);

    $request_body = array(
        'model' => $client_config['model'],
        'response_format' => array('type' => 'json_object'),
        'messages' => array(
            array('role' => 'system', 'content' => $system_prompt),
            array('role' => 'user', 'content' => json_encode($user_payload)),
        ),
    );

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $client_config['timeout']));
    curl_setopt($ch, CURLOPT_TIMEOUT, $client_config['timeout']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $client_config['api_key'],
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));

    $response_raw = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    curl_close($ch);

    hesk_aiLog('response_received', array(
        'request_id' => $request_id,
        'stage' => isset($log_context['stage']) ? $log_context['stage'] : '',
        'http_code' => $http_code,
        'curl_error' => $curl_error,
        'response_length' => is_string($response_raw) ? strlen($response_raw) : 0,
        'response_preview' => hesk_aiPreview(is_string($response_raw) ? $response_raw : '', 300),
    ));

    if ($response_raw === false || $curl_error !== '') {
        hesk_aiLog('failed', array(
            'request_id' => $request_id,
            'stage' => isset($log_context['stage']) ? $log_context['stage'] : '',
            'reason' => 'curl_error',
            'curl_error' => $curl_error,
        ));
        return array('ok' => false, 'reason' => 'curl_error');
    }

    if ($http_code < 200 || $http_code > 299) {
        $reason = 'http_' . $http_code;
        hesk_aiLog('failed', array(
            'request_id' => $request_id,
            'stage' => isset($log_context['stage']) ? $log_context['stage'] : '',
            'reason' => $reason,
        ));
        return array('ok' => false, 'reason' => $reason);
    }

    $response = json_decode($response_raw, true);
    if (!is_array($response)) {
        hesk_aiLog('failed', array(
            'request_id' => $request_id,
            'stage' => isset($log_context['stage']) ? $log_context['stage'] : '',
            'reason' => 'invalid_api_json',
        ));
        return array('ok' => false, 'reason' => 'invalid_api_json');
    }

    $content = '';
    if (isset($response['choices'][0]['message']['content'])) {
        $content = $response['choices'][0]['message']['content'];
    }

    if (!is_string($content) || trim($content) === '') {
        hesk_aiLog('failed', array(
            'request_id' => $request_id,
            'stage' => isset($log_context['stage']) ? $log_context['stage'] : '',
            'reason' => 'empty_model_response',
        ));
        return array('ok' => false, 'reason' => 'empty_model_response');
    }

    $prediction = json_decode($content, true);
    if (!is_array($prediction) && preg_match('/\{.*\}/s', $content, $m)) {
        $prediction = json_decode($m[0], true);
    }

    if (!is_array($prediction)) {
        hesk_aiLog('failed', array(
            'request_id' => $request_id,
            'stage' => isset($log_context['stage']) ? $log_context['stage'] : '',
            'reason' => 'invalid_model_json',
            'content_preview' => hesk_aiPreview($content, 300),
        ));
        return array('ok' => false, 'reason' => 'invalid_model_json');
    }

    return array(
        'ok' => true,
        'prediction' => $prediction,
    );
}

function hesk_aiLog($event, $context = array())
{
    global $hesk_settings;

    if (empty($hesk_settings['ai_auto_assign_log_enabled'])) {
        return;
    }

    $log_file = hesk_aiGetLogFilePath();
    if ($log_file === false) {
        return;
    }

    if (empty($hesk_settings['ai_auto_assign_log_payload'])) {
        foreach (array('subject_preview', 'message_preview', 'response_preview', 'content_preview', 'prediction') as $field) {
            if (isset($context[$field])) {
                unset($context[$field]);
            }
        }
    }

    $line = array(
        'ts' => gmdate('c'),
        'event' => $event,
        'context' => hesk_aiSanitizeLogContext($context),
    );

    @file_put_contents($log_file, json_encode($line, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

function hesk_aiGetLogFilePath()
{
    global $hesk_settings;

    $cache_dir = isset($hesk_settings['cache_dir']) ? trim($hesk_settings['cache_dir']) : 'cache';
    if ($cache_dir === '') {
        $cache_dir = 'cache';
    }

    return HESK_PATH . $cache_dir . '/ai_auto_assign.log';
}

function hesk_aiSanitizeLogContext($value)
{
    if (is_array($value)) {
        $out = array();
        foreach ($value as $k => $v) {
            if (stripos((string) $k, 'api_key') !== false || stripos((string) $k, 'authorization') !== false || stripos((string) $k, 'token') !== false) {
                $out[$k] = '[REDACTED]';
                continue;
            }

            $out[$k] = hesk_aiSanitizeLogContext($v);
        }

        return $out;
    }

    if (is_string($value) && strlen($value) > 2000) {
        return substr($value, 0, 2000) . '...[truncated]';
    }

    return $value;
}

function hesk_aiPreview($text, $max_len = 200)
{
    if (!is_string($text) || $text === '') {
        return '';
    }

    $text = preg_replace('/\s+/', ' ', trim($text));
    if (hesk_mb_strlen($text) <= $max_len) {
        return $text;
    }

    return hesk_mb_substr($text, 0, $max_len) . '...';
}

function hesk_aiGetCategoriesForAssignment($allowed_category_ids = array(), $category_type = -1)
{
    global $hesk_settings;

    $sql = "SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories`";

    $where = array();

    if ($category_type === 0 || $category_type === 1) {
        $where[] = "`type`='" . intval($category_type) . "'";
    }

    if (count($allowed_category_ids)) {
        $safe_ids = array();
        foreach ($allowed_category_ids as $id) {
            if ($id > 0) {
                $safe_ids[] = intval($id);
            }
        }

        if (!count($safe_ids)) {
            return array();
        }

        $where[] = '`id` IN (' . implode(',', $safe_ids) . ')';
    }

    if (count($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY `name` ASC';

    $res = hesk_dbQuery($sql);
    $out = array();
    $category_descriptions = hesk_aiGetCategoryDescriptionMap();

    while ($row = hesk_dbFetchAssoc($res)) {
        $category_id = intval($row['id']);
        $out[] = array(
            'id' => $category_id,
            'name' => $row['name'],
            'description' => isset($category_descriptions[$category_id]) ? $category_descriptions[$category_id] : '',
        );
    }

    return $out;
}

function hesk_aiGetCategoryDescriptionMap()
{
    global $hesk_settings;

    $raw = isset($hesk_settings['ai_auto_assign_category_descriptions'])
        ? trim((string) $hesk_settings['ai_auto_assign_category_descriptions'])
        : '';

    if ($raw === '') {
        return array();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return array();
    }

    $out = array();

    foreach ($decoded as $category_id => $description) {
        $category_id = intval($category_id);
        $description = trim((string) $description);

        if ($category_id < 1 || $description === '') {
            continue;
        }

        $out[$category_id] = $description;
    }

    return $out;
}

function hesk_aiGetUsersForDescriptionSettings()
{
    global $hesk_settings;

    $res = hesk_dbQuery("SELECT `user`.`id`, `user`.`name`, `user`.`user`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `user`
        WHERE `user`.`active` = 1
        ORDER BY `user`.`name` ASC, `user`.`user` ASC");

    $out = array();

    while ($row = hesk_dbFetchAssoc($res)) {
        $out[] = array(
            'id' => intval($row['id']),
            'name' => $row['name'],
            'user' => $row['user'],
        );
    }

    return $out;
}

function hesk_aiGetUsersForOwnerAssignment($category_id, $eligible_users = null)
{
    $user_descriptions = hesk_aiGetUserDescriptionMap();
    if (!is_array($eligible_users)) {
        $eligible_users = hesk_getAutoAssignEligibleUsers($category_id);
    }
    $out = array();

    foreach ($eligible_users as $user) {
        $user_id = intval($user['id']);
        $out[] = array(
            'id' => $user_id,
            'name' => isset($user['name']) ? $user['name'] : '',
            'user' => isset($user['user']) ? $user['user'] : '',
            'description' => isset($user_descriptions[$user_id]) ? $user_descriptions[$user_id] : '',
        );
    }

    return $out;
}

function hesk_aiGetUserDescriptionMap()
{
    global $hesk_settings;

    $raw = isset($hesk_settings['ai_auto_assign_user_descriptions'])
        ? trim((string) $hesk_settings['ai_auto_assign_user_descriptions'])
        : '';

    if ($raw === '') {
        return array();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return array();
    }

    $out = array();

    foreach ($decoded as $user_id => $description) {
        $user_id = intval($user_id);
        $description = trim((string) $description);

        if ($user_id < 1 || $description === '') {
            continue;
        }

        $out[$user_id] = $description;
    }

    return $out;
}

function hesk_aiGetPrioritiesForAssignment()
{
    global $hesk_settings;

    if (!isset($hesk_settings['priorities'])) {
        require_once(HESK_PATH . 'inc/priorities.inc.php');
    }

    $out = array();

    foreach ($hesk_settings['priorities'] as $id => $priority) {
        $out[] = array(
            'id' => intval($id),
            'name' => isset($priority['name']) ? $priority['name'] : ('priority_' . intval($id)),
        );
    }

    return $out;
}
