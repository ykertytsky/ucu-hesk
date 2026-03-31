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

define('IN_SCRIPT',1);
define('HESK_PATH','../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/ai_assignment.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Check permissions for this feature
hesk_checkPermission('can_man_settings');

function hesk_aiModuleReadLogTail($path, $max_lines = 25)
{
    if (!is_string($path) || $path === '' || !is_readable($path)) {
        return '';
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines) || !count($lines)) {
        return '';
    }

    return implode("\n", array_slice($lines, -$max_lines));
}

function hesk_aiModuleReadLogEntries($path, $max_lines = 15)
{
    if (!is_string($path) || $path === '' || !is_readable($path)) {
        return array();
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines) || !count($lines)) {
        return array();
    }

    $tail = array_slice($lines, -$max_lines);
    $entries = array();

    foreach ($tail as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }

        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            $entries[] = array(
                'ts' => '',
                'event' => 'invalid_json',
                'reason' => 'invalid_json',
                'confidence' => null,
                'http_code' => null,
                'request_id' => null,
            );
            continue;
        }

        $ctx = isset($decoded['context']) && is_array($decoded['context']) ? $decoded['context'] : array();

        $entries[] = array(
            'ts' => isset($decoded['ts']) ? (string) $decoded['ts'] : '',
            'event' => isset($decoded['event']) ? (string) $decoded['event'] : '',
            'reason' => isset($ctx['reason']) ? (string) $ctx['reason'] : (isset($ctx['curl_error']) ? (string) $ctx['curl_error'] : ''),
            'confidence' => array_key_exists('confidence', $ctx) ? $ctx['confidence'] : null,
            'http_code' => isset($ctx['http_code']) ? $ctx['http_code'] : null,
            'request_id' => isset($ctx['request_id']) ? (string) $ctx['request_id'] : null,
        );
    }

    return $entries;
}

$log_file = hesk_aiGetLogFilePath();
if (isset($_GET['a']) && $_GET['a'] === 'download_log') {
    hesk_token_check('GET');

    if ($log_file === false || !is_file($log_file) || !is_readable($log_file)) {
        hesk_process_messages($hesklang['ai']['log_missing'], 'module_ai.php', 'NOTICE');
    }

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ai_auto_assign.log"');
    header('Content-Length: ' . filesize($log_file));
    readfile($log_file);
    exit();
}

// Demo mode? Hide values of sensitive settings
if ( defined('HESK_DEMO') )
{
    require_once(HESK_PATH . 'inc/admin_settings_demo.inc.php');
}

$ai_enabled = isset($hesk_settings['ai_auto_assign_enabled']) ? intval($hesk_settings['ai_auto_assign_enabled']) : 0;
$ai_model = isset($hesk_settings['ai_auto_assign_model']) ? $hesk_settings['ai_auto_assign_model'] : 'gpt-4o-mini';
$ai_timeout = isset($hesk_settings['ai_auto_assign_timeout']) ? intval($hesk_settings['ai_auto_assign_timeout']) : 10;
$ai_min_confidence = isset($hesk_settings['ai_auto_assign_min_confidence']) ? floatval($hesk_settings['ai_auto_assign_min_confidence']) : 0.60;
$ai_owner_enabled = isset($hesk_settings['ai_auto_assign_owner_enabled']) ? intval($hesk_settings['ai_auto_assign_owner_enabled']) : 0;
$ai_owner_min_confidence = isset($hesk_settings['ai_auto_assign_owner_min_confidence']) ? floatval($hesk_settings['ai_auto_assign_owner_min_confidence']) : 0.85;
$ai_override_category = isset($hesk_settings['ai_auto_assign_override_category']) ? intval($hesk_settings['ai_auto_assign_override_category']) : 1;
$ai_override_priority = isset($hesk_settings['ai_auto_assign_override_priority']) ? intval($hesk_settings['ai_auto_assign_override_priority']) : 1;
$ai_override_owner = isset($hesk_settings['ai_auto_assign_override_owner']) ? intval($hesk_settings['ai_auto_assign_override_owner']) : 0;
$ai_include_email_piping = isset($hesk_settings['ai_auto_assign_include_email_piping']) ? intval($hesk_settings['ai_auto_assign_include_email_piping']) : 0;
$ai_debug = isset($hesk_settings['ai_auto_assign_debug']) ? intval($hesk_settings['ai_auto_assign_debug']) : 0;
$ai_log_enabled = isset($hesk_settings['ai_auto_assign_log_enabled']) ? intval($hesk_settings['ai_auto_assign_log_enabled']) : 0;
$ai_log_payload = isset($hesk_settings['ai_auto_assign_log_payload']) ? intval($hesk_settings['ai_auto_assign_log_payload']) : 0;
$ai_api_key = isset($hesk_settings['ai_auto_assign_api_key']) ? $hesk_settings['ai_auto_assign_api_key'] : '';
$ai_category_descriptions = hesk_aiGetCategoryDescriptionMap();
$ai_user_descriptions = hesk_aiGetUserDescriptionMap();
$ai_categories = hesk_aiGetCategoriesForAssignment();
$ai_users = hesk_aiGetUsersForDescriptionSettings();
$ai_div = $ai_enabled ? 'block' : 'none';

$log_exists = $log_file !== false && is_file($log_file) && is_readable($log_file);
$log_size = $log_exists ? filesize($log_file) : null;
$log_size_display = $log_exists ? number_format($log_size) . ' B' : '';
$log_entries = $log_exists ? hesk_aiModuleReadLogEntries($log_file, 20) : array();

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

/* This will handle error, success and notice messages */
hesk_handle_messages();
?>
<div class="main__content settings module_ai">
    <?php require_once(HESK_PATH . 'inc/admin_settings_status.inc.php'); ?>
    <script language="javascript" type="text/javascript"><!--
        function hesk_aiSubmitForm()
        {
            document.form1.submitbutton.disabled = true;
            return true;
        }
        //-->
    </script>

    <form method="post" action="admin_settings_save.php" name="form1" onsubmit="return hesk_aiSubmitForm()">
        <div class="settings__form form">
            <h3><?php echo $hesklang['ai']['tab']; ?></h3>
            <div class="added-left-offset"><?php echo $hesklang['ai']['intro']; ?></div>

            <section class="settings__form_block">
                <h3><?php echo $hesklang['ai']['auto_assign_title']; ?></h3>
                <div class="checkbox-group">
                    <h5>
                        <span><?php echo $hesklang['ai']['enable_auto_assign']; ?></span>
                    </h5>
                    <label class="switch-checkbox">
                        <input type="checkbox" name="s_ai_auto_assign_enabled" value="1" <?php if ($ai_enabled) { echo 'checked'; } ?> onclick="hesk_toggleLayerDisplay('ai_auto_assign')">
                        <div class="switch-checkbox__bullet">
                            <i>
                                <svg class="icon icon-close">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                </svg>
                                <svg class="icon icon-tick">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tick"></use>
                                </svg>
                            </i>
                        </div>
                    </label>
                </div>
                <div id="ai_auto_assign" style="display: <?php echo $ai_div; ?>;">
                    <div class="form-group">
                        <label>
                            <span><?php echo $hesklang['ai']['model']; ?></span>
                        </label>
                        <input type="text" class="form-control" name="s_ai_auto_assign_model" maxlength="100" value="<?php echo hesk_htmlspecialchars($ai_model); ?>">
                    </div>
                    <div class="form-group short">
                        <label>
                            <span><?php echo $hesklang['ai']['timeout']; ?></span>
                        </label>
                        <input type="text" class="form-control" name="s_ai_auto_assign_timeout" maxlength="2" value="<?php echo intval($ai_timeout); ?>">
                    </div>
                    <div class="form-group short">
                        <label>
                            <span><?php echo $hesklang['ai']['min_confidence']; ?></span>
                        </label>
                        <input type="text" class="form-control" name="s_ai_auto_assign_min_confidence" maxlength="4" value="<?php echo number_format($ai_min_confidence, 2, '.', ''); ?>">
                    </div>
                    <div class="form-group short">
                        <label>
                            <span><?php echo $hesklang['ai']['owner_min_confidence']; ?></span>
                        </label>
                        <input type="text" class="form-control" name="s_ai_auto_assign_owner_min_confidence" maxlength="4" value="<?php echo number_format($ai_owner_min_confidence, 2, '.', ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>
                            <span><?php echo $hesklang['ai']['api_key']; ?></span>
                        </label>
                        <input type="text" class="form-control" name="s_ai_auto_assign_api_key" maxlength="255" value="<?php echo hesk_htmlspecialchars($ai_api_key); ?>">
                    </div>
                    <div class="checkbox-group list ai-behavior">
                        <h5>
                            <span><?php echo $hesklang['ai']['behavior']; ?></span>
                        </h5>
                        <div class="checkbox-list">
                            <div class="checkbox-custom">
                                <input type="checkbox" id="s_ai_auto_assign_owner_enabled" name="s_ai_auto_assign_owner_enabled" value="1" <?php if ($ai_owner_enabled) { echo 'checked'; } ?>>
                                <label for="s_ai_auto_assign_owner_enabled"><?php echo $hesklang['ai']['owner_enabled']; ?></label>
                            </div>
                            <div class="checkbox-custom">
                                <input type="checkbox" id="s_ai_auto_assign_override_category" name="s_ai_auto_assign_override_category" value="1" <?php if ($ai_override_category) { echo 'checked'; } ?>>
                                <label for="s_ai_auto_assign_override_category"><?php echo $hesklang['ai']['override_category']; ?></label>
                            </div>
                            <div class="checkbox-custom">
                                <input type="checkbox" id="s_ai_auto_assign_override_priority" name="s_ai_auto_assign_override_priority" value="1" <?php if ($ai_override_priority) { echo 'checked'; } ?>>
                                <label for="s_ai_auto_assign_override_priority"><?php echo $hesklang['ai']['override_priority']; ?></label>
                            </div>
                            <div class="checkbox-custom">
                                <input type="checkbox" id="s_ai_auto_assign_override_owner" name="s_ai_auto_assign_override_owner" value="1" <?php if ($ai_override_owner) { echo 'checked'; } ?>>
                                <label for="s_ai_auto_assign_override_owner"><?php echo $hesklang['ai']['override_owner']; ?></label>
                            </div>
                            <div class="checkbox-custom">
                                <input type="checkbox" id="s_ai_auto_assign_include_email_piping" name="s_ai_auto_assign_include_email_piping" value="1" <?php if ($ai_include_email_piping) { echo 'checked'; } ?>>
                                <label for="s_ai_auto_assign_include_email_piping"><?php echo $hesklang['ai']['include_email_piping']; ?></label>
                            </div>
                            <div class="checkbox-custom">
                                <input type="checkbox" id="s_ai_auto_assign_debug" name="s_ai_auto_assign_debug" value="1" <?php if ($ai_debug) { echo 'checked'; } ?>>
                                <label for="s_ai_auto_assign_debug"><?php echo $hesklang['ai']['debug']; ?></label>
                            </div>
                            <div class="checkbox-custom">
                                <input type="checkbox" id="s_ai_auto_assign_log_enabled" name="s_ai_auto_assign_log_enabled" value="1" <?php if ($ai_log_enabled) { echo 'checked'; } ?>>
                                <label for="s_ai_auto_assign_log_enabled"><?php echo $hesklang['ai']['log_enabled']; ?></label>
                            </div>
                            <div class="checkbox-custom">
                                <input type="checkbox" id="s_ai_auto_assign_log_payload" name="s_ai_auto_assign_log_payload" value="1" <?php if ($ai_log_payload) { echo 'checked'; } ?>>
                                <label for="s_ai_auto_assign_log_payload"><?php echo $hesklang['ai']['log_payload']; ?></label>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="settings__form_block">
                <h3><?php echo $hesklang['ai']['logs_title']; ?></h3>
                <div class="added-left-offset"><?php echo $hesklang['ai']['logs_intro']; ?></div>

                <div class="form-group">
                    <label>
                        <span><?php echo $hesklang['ai']['log_file']; ?></span>
                    </label>
                    <span><code><?php echo HESK_PATH . $hesk_settings['cache_dir']; ?>/ai_auto_assign.log</code></span>
                </div>

                <div class="form-group short">
                    <label>
                        <span><?php echo $hesklang['ai']['log_status']; ?></span>
                    </label>
                    <span><?php echo $log_exists ? $hesklang['ai']['log_available'] : $hesklang['ai']['log_unavailable']; ?></span>
                </div>

                <?php if ($log_exists): ?>
                <div class="form-group short">
                    <label>
                        <span><?php echo $hesklang['ai']['log_size']; ?></span>
                    </label>
                    <span><?php echo $log_size_display; ?></span>
                </div>
                <?php endif; ?>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Event</th>
                                <th>Reason</th>
                                <th>Confidence</th>
                                <th>Request</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$log_exists): ?>
                                <tr>
                                    <td colspan="5"><?php echo $hesklang['ai']['log_missing']; ?></td>
                                </tr>
                            <?php elseif (empty($log_entries)): ?>
                                <tr>
                                    <td colspan="5"><?php echo $hesklang['ai']['log_empty']; ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($log_entries as $entry): ?>
                                    <tr>
                                        <td><?php echo hesk_htmlspecialchars((string) (isset($entry['ts']) ? $entry['ts'] : '')); ?></td>
                                        <td><?php echo hesk_htmlspecialchars((string) (isset($entry['event']) ? $entry['event'] : '')); ?></td>
                                        <td><?php echo hesk_htmlspecialchars(hesk_aiPreview((string) ($entry['reason'] ?? ''), 120)); ?></td>
                                        <td><?php echo isset($entry['confidence']) && $entry['confidence'] !== null ? hesk_htmlspecialchars((string) $entry['confidence']) : ''; ?></td>
                                        <td>
                                            <?php
                                            $rid = isset($entry['request_id']) ? (string) $entry['request_id'] : '';
                                            echo hesk_htmlspecialchars($rid);
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="settings__form_block ai-description-list ai-category-descriptions">
                <h3><?php echo $hesklang['ai']['category_descriptions_title']; ?></h3>
                <div class="added-left-offset"><?php echo $hesklang['ai']['category_descriptions_intro']; ?></div>

                <?php if (empty($ai_categories)): ?>
                <div class="added-left-offset"><?php echo $hesklang['ai']['category_descriptions_empty']; ?></div>
                <?php else: ?>
                    <?php foreach ($ai_categories as $category): ?>
                    <?php
                    $category_id = isset($category['id']) ? intval($category['id']) : 0;
                    $category_name = isset($category['name']) ? (string) $category['name'] : '';
                    $category_description = isset($ai_category_descriptions[$category_id]) ? $ai_category_descriptions[$category_id] : '';
                    ?>
                    <div class="form-group list">
                        <label for="s_ai_auto_assign_category_description_<?php echo $category_id; ?>">
                            <span><?php echo hesk_htmlspecialchars($category_name); ?></span>
                        </label>
                        <textarea
                            class="form-control"
                            id="s_ai_auto_assign_category_description_<?php echo $category_id; ?>"
                            name="s_ai_auto_assign_category_descriptions[<?php echo $category_id; ?>]"
                            rows="3"
                            maxlength="1000"
                            placeholder="<?php echo hesk_htmlspecialchars($hesklang['ai']['category_description_placeholder']); ?>"><?php echo hesk_htmlspecialchars($category_description); ?></textarea>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="settings__form_block ai-description-list ai-user-descriptions">
                <h3><?php echo $hesklang['ai']['user_descriptions_title']; ?></h3>
                <div class="added-left-offset"><?php echo $hesklang['ai']['user_descriptions_intro']; ?></div>

                <?php if (empty($ai_users)): ?>
                <div class="added-left-offset"><?php echo $hesklang['ai']['user_descriptions_empty']; ?></div>
                <?php else: ?>
                    <?php foreach ($ai_users as $user): ?>
                    <?php
                    $user_id = isset($user['id']) ? intval($user['id']) : 0;
                    $user_name = isset($user['name']) ? (string) $user['name'] : '';
                    $user_login = isset($user['user']) ? (string) $user['user'] : '';
                    $user_label = $user_login !== '' ? $user_name . ' (' . $user_login . ')' : $user_name;
                    $user_description = isset($ai_user_descriptions[$user_id]) ? $ai_user_descriptions[$user_id] : '';
                    ?>
                    <div class="form-group list">
                        <label for="s_ai_auto_assign_user_description_<?php echo $user_id; ?>">
                            <span><?php echo hesk_htmlspecialchars($user_label); ?></span>
                        </label>
                        <textarea
                            class="form-control"
                            id="s_ai_auto_assign_user_description_<?php echo $user_id; ?>"
                            name="s_ai_auto_assign_user_descriptions[<?php echo $user_id; ?>]"
                            rows="3"
                            maxlength="1000"
                            placeholder="<?php echo hesk_htmlspecialchars($hesklang['ai']['user_description_placeholder']); ?>"><?php echo hesk_htmlspecialchars($user_description); ?></textarea>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <div class="settings__form_submit">
                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                <input type="hidden" name="section" value="AI">
                <button style="display: inline-flex" type="submit" id="submitbutton" class="btn btn-full" ripple="ripple">
                    <?php echo $hesklang['save_changes']; ?>
                </button>
            </div>
        </div>
    </form>
</div>

<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();
