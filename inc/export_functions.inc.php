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

/*** FUNCTIONS ***/

function hesk_export_to_XML($sql, $export_selected = false, $export_history = false, $export_replies = false)
{
    global $hesk_settings, $hesklang;

    list($save_to, $tickets_exported, $export_name, $flush_me) = hesk_export_build_xml_file($sql, $export_selected, $export_history, $export_replies);

    if ($tickets_exported < 1)
    {
        return array('', 0);
    }

    $flush_me .= hesk_date() . " | {$hesklang['cZIP']}<br />\n";
    $save_to_zip = hesk_export_zip_xml_file($save_to, $export_name);

    // Delete XML, just leave the Zip archive
    hesk_unlink($save_to);

    // Echo memory peak usage
    $flush_me .= hesk_date() . " | " . sprintf($hesklang['pmem'], (@memory_get_peak_usage(true) / 1048576)) . "<br />\r\n";

    // We're done!
    $flush_me .= hesk_date() . " | {$hesklang['fZIP']}<br /><br />";

    // Success message
    $referer = isset($_SERVER['HTTP_REFERER']) ? hesk_input($_SERVER['HTTP_REFERER']) : 'export.php';
    $referer = str_replace('&amp;','&',$referer);
    if (strpos($referer, 'export.php'))
    {
        $referer = 'export.php';
    }

    $success_msg = $hesk_settings['debug_mode'] ? $flush_me : '<br /><br />';
    $success_msg .= $hesklang['step1'] . ': <a href="' . $save_to_zip . '">' . $hesklang['ch2d'] . '</a><br /><br />' . $hesklang['step2'] . ': <a href="export.php?delete='.urlencode($export_name).'&amp;goto='.urlencode($referer).'">' . $hesklang['dffs'] . '</a>';

    return array($success_msg, $tickets_exported);

} // END hesk_export_to_XML()


function hesk_export_push_to_dashboard($sql, $export_selected = false, $export_history = false, $export_replies = false)
{
    global $hesk_settings, $hesklang;

    $attempted_at = date('Y-m-d H:i:s');
    $result = array(
        'success' => false,
        'tickets_exported' => 0,
        'http_code' => 0,
        'error_message' => '',
    );

    if (empty($hesk_settings['dashboard_export_url']))
    {
        $result['error_message'] = $hesklang['dashboard_sync_not_configured'];
        hesk_write_dashboard_sync_state(array(
            'last_attempt_at' => $attempted_at,
            'last_http_code' => 0,
            'last_error' => $result['error_message'],
            'last_ticket_count' => 0,
        ));

        return $result;
    }

    list($save_to, $tickets_exported, $export_name) = hesk_export_build_xml_file($sql, $export_selected, $export_history, $export_replies);
    $result['tickets_exported'] = $tickets_exported;

    if ($tickets_exported < 1)
    {
        $result['error_message'] = $hesklang['n2ex'];
        hesk_write_dashboard_sync_state(array(
            'last_attempt_at' => $attempted_at,
            'last_http_code' => 0,
            'last_error' => $result['error_message'],
            'last_ticket_count' => 0,
        ));

        return $result;
    }

    if (!function_exists('curl_init'))
    {
        hesk_unlink($save_to);

        $result['error_message'] = $hesklang['dashboard_sync_curl_missing'];
        hesk_write_dashboard_sync_state(array(
            'last_attempt_at' => $attempted_at,
            'last_http_code' => 0,
            'last_error' => $result['error_message'],
            'last_ticket_count' => $tickets_exported,
        ));

        return $result;
    }

    if ( ! class_exists('CURLFile'))
    {
        hesk_unlink($save_to);

        $result['error_message'] = $hesklang['dashboard_sync_curlfile_missing'];
        hesk_write_dashboard_sync_state(array(
            'last_attempt_at' => $attempted_at,
            'last_http_code' => 0,
            'last_error' => $result['error_message'],
            'last_ticket_count' => $tickets_exported,
        ));

        return $result;
    }

    if ( ! is_readable($save_to))
    {
        hesk_unlink($save_to);

        $result['error_message'] = $hesklang['dashboard_sync_read_failed'];
        hesk_write_dashboard_sync_state(array(
            'last_attempt_at' => $attempted_at,
            'last_http_code' => 0,
            'last_error' => $result['error_message'],
            'last_ticket_count' => $tickets_exported,
        ));

        return $result;
    }

    // Next.js route expects multipart/form-data with field name "file" (see request.formData() / formData.get("file")).
    // Do not send Content-Type yourself — cURL sets multipart boundary + Content-Type.
    $upload_basename = $export_name . '.xml';
    $post_fields = array(
        'file' => new CURLFile($save_to, 'application/xml', $upload_basename),
    );

    // Expect: 100-continue on large POST bodies often breaks reverse proxies and some app servers (RST mid-read).
    $headers = array(
        'Expect:',
    );

    if (!empty($hesk_settings['dashboard_export_token']))
    {
        $headers[] = 'Authorization: Bearer ' . $hesk_settings['dashboard_export_token'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $hesk_settings['dashboard_export_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERAGENT, 'HESK-DashboardExport/' . (isset($hesk_settings['hesk_version']) ? $hesk_settings['hesk_version'] : '3'));
    if (defined('CURL_HTTP_VERSION_1_1'))
    {
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    }
    if (defined('CURLOPT_NOSIGNAL'))
    {
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    }

    $response = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    hesk_curl_close($ch);

    hesk_unlink($save_to);

    $result['http_code'] = $http_code;

    if ($response === false)
    {
        $result['error_message'] = $curl_error ? $curl_error : $hesklang['dashboard_sync_request_failed'];
        hesk_write_dashboard_sync_state(array(
            'last_attempt_at' => $attempted_at,
            'last_http_code' => $http_code,
            'last_error' => $result['error_message'],
            'last_ticket_count' => $tickets_exported,
        ));

        return $result;
    }

    if ($http_code < 200 || $http_code >= 300)
    {
        $msg = sprintf($hesklang['dashboard_sync_http_error'], $http_code);
        if ($response !== false && strlen($response))
        {
            $decoded = json_decode($response, true);
            if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error']) && strlen($decoded['error']))
            {
                $msg .= ' ' . sprintf($hesklang['dashboard_sync_remote_error'], $decoded['error']);
            }
        }
        $result['error_message'] = $msg;
        hesk_write_dashboard_sync_state(array(
            'last_attempt_at' => $attempted_at,
            'last_http_code' => $http_code,
            'last_error' => $result['error_message'],
            'last_ticket_count' => $tickets_exported,
        ));

        return $result;
    }

    $result['success'] = true;
    hesk_write_dashboard_sync_state(array(
        'last_attempt_at' => $attempted_at,
        'last_success_at' => $attempted_at,
        'last_http_code' => $http_code,
        'last_error' => '',
        'last_ticket_count' => $tickets_exported,
    ));

    return $result;

} // END hesk_export_push_to_dashboard()


function hesk_export_build_xml_file($sql, $export_selected = false, $export_history = false, $export_replies = false)
{
    global $hesk_settings, $hesklang, $ticket, $my_cat;

	// We'll need HH:MM:SS format for hesk_date() here
	$hesk_settings['format_timestamp'] = 'H:i:s';

	// Get staff names
	$admins = array();
	$result = hesk_dbQuery("SELECT `id`,`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` ORDER BY `name` ASC");
	while ($row=hesk_dbFetchAssoc($result))
	{
		$admins[$row['id']]=hesk_msgToPlain($row['name'], 1, 0);
	}

    // Get category names
    if ( ! isset($my_cat))
    {
        $my_cat = array();
        $res2 = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE " . hesk_myCategories('id') . " ORDER BY `cat_order` ASC");
        while ($row=hesk_dbFetchAssoc($res2))
        {
            $my_cat[$row['id']] = hesk_msgToPlain($row['name'], 1, 0);
        }
    }

	// This will be the export directory
	$export_dir = hesk_export_get_cache_dir();

	// This will be the name of the export and the XML file
    $export_name = 'hesk_export_'.date('Y-m-d_H-i-s').'_'.mt_rand(100000,999999);
    $save_to = $export_dir . $export_name . '.xml';

	// Do we have the export directory?
    hesk_prepare_export_cache_dir($export_dir);

	// Make sure the file can be saved and written to
	@file_put_contents($save_to, '');
	if ( ! file_exists($save_to) )
	{
		hesk_error($hesklang['eef']);
	}

	// Start generating the report message and generating the export
	$flush_me = '<br /><br />';
	$flush_me .= hesk_date() . " | {$hesklang['inite']} ";

    // Is this export of a date or date range?
    if ($export_selected === false)
    {
        global $date_from, $date_to;

        if ($date_from == $date_to)
        {
            $flush_me .= "(" . hesk_date($date_from, true, true, true, $hesk_settings['format_date']) . ")";
        }
        else
        {
            $flush_me .= "(" . hesk_date($date_from, true, true, true, $hesk_settings['format_date']) . " - " . hesk_date($date_to, true, true, true, $hesk_settings['format_date']) . ")";
        }
    }

    $flush_me .= "<br />\n";

	// Start generating file contents
    $tmp = '<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <OfficeDocumentSettings xmlns="urn:schemas-microsoft-com:office:office">
  <AllowPNG/>
 </OfficeDocumentSettings>
 <ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
  <WindowHeight>8250</WindowHeight>
  <WindowWidth>16275</WindowWidth>
  <WindowTopX>360</WindowTopX>
  <WindowTopY>90</WindowTopY>
  <ProtectStructure>False</ProtectStructure>
  <ProtectWindows>False</ProtectWindows>
 </ExcelWorkbook>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Bottom"/>
   <Borders/>
   <Font ss:FontName="Calibri" x:CharSet="238" x:Family="Swiss" ss:Size="11"
    ss:Color="#000000"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="s62">
   <NumberFormat ss:Format="General Date"/>
  </Style>
  <Style ss:ID="s63">
   <NumberFormat ss:Format="Short Date"/>
  </Style>
  <Style ss:ID="s65">
   <NumberFormat ss:Format="[h]:mm:ss"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Sheet1">
  <Table>
';

	// Define column width
	$tmp .= '
	<Column ss:AutoFitWidth="0" ss:Width="50"/>
	<Column ss:AutoFitWidth="0" ss:Width="84"/>
	<Column ss:AutoFitWidth="0" ss:Width="100"/>
	<Column ss:AutoFitWidth="0" ss:Width="100"/>
	<Column ss:AutoFitWidth="0" ss:Width="100"/>
	<Column ss:AutoFitWidth="0" ss:Width="100"/>
	<Column ss:AutoFitWidth="0" ss:Width="90"/>
	<Column ss:AutoFitWidth="0" ss:Width="90"/>
	<Column ss:AutoFitWidth="0" ss:Width="90"/>
	<Column ss:AutoFitWidth="0" ss:Width="87"/>
	<Column ss:AutoFitWidth="0" ss:Width="57.75"/>
	<Column ss:AutoFitWidth="0" ss:Width="57.75"/>
	<Column ss:AutoFitWidth="0" ss:Width="100"/>
	<Column ss:AutoFitWidth="0" ss:Width="100"/>
	<Column ss:AutoFitWidth="0" ss:Width="80"/>
	<Column ss:AutoFitWidth="0" ss:Width="50"/>
	<Column ss:AutoFitWidth="0" ss:Width="50"/>
	<Column ss:AutoFitWidth="0" ss:Width="70"/>
	<Column ss:AutoFitWidth="0" ss:Width="70"/>
	';

	foreach ($hesk_settings['custom_fields'] as $k=>$v)
	{
		if ($v['use'])
		{
			$tmp .= '<Column ss:AutoFitWidth="0" ss:Width="80"/>' . "\n";
		}
	}

    if ($export_history) {
        $tmp .= '<Column ss:AutoFitWidth="0" ss:Width="100"/>' . "\n";
    }

    if ($export_replies) {
        $tmp .= '<Column ss:AutoFitWidth="0" ss:Width="100"/>' . "\n";
    }


	// Define first row (header)
	$tmp .= '
	<Row>
	<Cell><Data ss:Type="String">#</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['trackID'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['date'].'</Data></Cell>
    <Cell><Data ss:Type="String">'.$hesklang['last_update'].'</Data></Cell>
    <Cell><Data ss:Type="String">'.$hesklang['first_reply_at'].'</Data></Cell>
    <Cell><Data ss:Type="String">'.$hesklang['resolved_at'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['name'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['email'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['followers'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['category'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['priority'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['status'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['subject'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['message'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['owner'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['replies'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['replies'] . ' (' . $hesklang['staff'] .')'.'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['ts'].'</Data></Cell>
	<Cell><Data ss:Type="String">'.$hesklang['due_date'].'</Data></Cell>
	';

	foreach ($hesk_settings['custom_fields'] as $k=>$v)
	{
		if ($v['use'])
		{
			$tmp .= '<Cell><Data ss:Type="String">'.$v['name'].'</Data></Cell>' . "\n";
		}
	}

    if ($export_history) {
        $tmp .= '<Cell><Data ss:Type="String">'.$hesklang['thist'].'</Data></Cell>' . "\n";
    }

    if ($export_replies) {
        $tmp .= '<Cell><Data ss:Type="String">'.$hesklang['reply_messages'].'</Data></Cell>' . "\n";
    }

    $tmp .= '<Cell><Data ss:Type="String">'.$hesklang['ticket_url'].'</Data></Cell>' . "\n";
	$tmp .= "</Row>\n";

	// Write what we have by now into the XML file
	file_put_contents($save_to, $tmp, FILE_APPEND);
	$flush_me .= hesk_date() . " | {$hesklang['gXML']}<br />\n";

	// OK, now start dumping data and writing it into the file
    $tickets_exported = 0;
	$save_after = 100;
    $this_round = 0;
    $tmp = '';

    $result = hesk_dbQuery($sql);
	while ($ticket=hesk_dbFetchAssoc($result))
	{
        $ticket['status'] = hesk_get_status_name($ticket['status']);
        $ticket['priority'] = hesk_get_priority_name($ticket['priority']);
		$ticket['archive'] = !($ticket['archive']) ? $hesklang['no'] : $hesklang['yes'];
		$ticket['message'] = hesk_msgToPlain($ticket['message'], 1, 0);
		$ticket['subject'] = hesk_msgToPlain($ticket['subject'], 1, 0);
        $ticket['owner'] = isset($admins[$ticket['owner']]) ? $admins[$ticket['owner']] : '';
		$ticket['category'] = isset($my_cat[$ticket['category']]) ? $my_cat[$ticket['category']] : '';

        if (!function_exists('hesk_get_customers_for_ticket')) {
            require_once(HESK_PATH . 'inc/customer_accounts.inc.php');
        }
        $customers = hesk_get_customers_for_ticket($ticket['id']);
        if (defined('HESK_DEMO')) {
            array_walk($customers, function(&$k) {
                $k['email'] = 'hidden@demo.com';
            });
        }
        $found_requester = false;
        $requester = [];
        $followers = [];
        foreach ($customers as $customer) {
            if ($customer['customer_type'] === 'REQUESTER') {
                $found_requester = true;
                $requester = $customer;
            } elseif ($customer['customer_type'] === 'FOLLOWER') {
                $followers[] = $customer;
            }
        }
        if (!$found_requester) {
            // Ticket has been anonymized
            $requester['name'] = $hesklang['anon_name'];
            $requester['email'] = $hesklang['anon_email'];
        }
        $follower_names = array_map(function($follower) { return format_display_name($follower); }, $followers);

		// Format for export dates
		$hesk_settings['format_timestamp'] = "Y-m-d\TH:i:s\.000";

		// Create row for the XML file
		$tmp .= '
<Row>
<Cell><Data ss:Type="Number">'.$ticket['id'].'</Data></Cell>
<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA($ticket['trackid']).']]></Data></Cell>
<Cell ss:StyleID="s62"><Data ss:Type="DateTime">'.hesk_date($ticket['dt'], true).'</Data></Cell>
<Cell ss:StyleID="s62"><Data ss:Type="DateTime">'.hesk_date($ticket['lastchange'], true).'</Data></Cell>
<Cell ss:StyleID="s62"><Data ss:Type="DateTime">'.hesk_date($ticket['firstreply'], true).'</Data></Cell>
';

        if (empty($ticket['closedat'])) {
            $tmp .= '<Cell><Data ss:Type="String"></Data></Cell>'."\n";
        } else {
            $tmp .= '<Cell ss:StyleID="s62"><Data ss:Type="DateTime">'.hesk_date($ticket['closedat'], true).'</Data></Cell>'."\n";
        }

        $tmp .= '<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA(hesk_msgToPlain($requester['name'], 1, 0)).']]></Data></Cell>
<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA($requester['email']).']]></Data></Cell>
<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA(implode(',', $follower_names)).']]></Data></Cell>
<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA($ticket['category']).']]></Data></Cell>
<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA($ticket['priority']).']]></Data></Cell>
<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA($ticket['status']).']]></Data></Cell>
<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA($ticket['subject']).']]></Data></Cell>
<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA($ticket['message']).']]></Data></Cell>
<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA($ticket['owner']).']]></Data></Cell>
<Cell><Data ss:Type="Number">'.$ticket['replies'].'</Data></Cell>
<Cell><Data ss:Type="Number">'.$ticket['staffreplies'].'</Data></Cell>
<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA($ticket['time_worked']).']]></Data></Cell>
';

        // Due date
        if (empty($ticket['due_date']))
        {
            $tmp .= '<Cell><Data ss:Type="String"></Data></Cell>'."\n";
        }
        else
        {
            $tmp .= '<Cell ss:StyleID="s63"><Data ss:Type="DateTime">'.hesk_date($ticket['due_date'], true).'</Data></Cell>'."\n";
        }

		// Add custom fields
		foreach ($hesk_settings['custom_fields'] as $k=>$v)
		{
			if ($v['use'])
			{
	        	switch ($v['type'])
	            {
	            	case 'date':
                    	$tmp_dt = hesk_custom_date_display_format($ticket[$k], 'Y-m-d\T00:00:00.000');
	                	$tmp .= strlen($tmp_dt) ? '<Cell ss:StyleID="s63"><Data ss:Type="DateTime">'.$tmp_dt : '<Cell><Data ss:Type="String">';
                        $tmp .= "</Data></Cell> \n";
	                    break;
	                default:
						$tmp .= '<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA(hesk_msgToPlain($ticket[$k], 1, 0)).']]></Data></Cell>  ' . "\n";
	            }
			}
		}

        if ($export_history) {
            $tmp .= '<Cell><Data ss:Type="String"><![CDATA['.hesk_escape_CDATA($ticket['history']).']]></Data></Cell>' . "\n";
        }

        if ($export_replies) {

            $tmp .= '<Cell><Data ss:Type="String"><![CDATA[';

            if ($ticket['replies']) {
                $replies = hesk_dbQuery("SELECT `replies`.*, `customers`.`name` AS `customer_name`, `customers`.`email` AS `customer_email`, `users`.`name` AS `staff_name`
                    FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` AS `replies`
                    LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."customers` AS `customers`
                        ON `customers`.`id` = `replies`.`customer_id`
                    LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `users`
                        ON `users`.`id` = `replies`.`staffid`
                    WHERE `replyto`='".intval($ticket['id'])."' ORDER BY `id` " . ($hesk_settings['new_top'] ? 'DESC' : 'ASC') );

                while ($reply = hesk_dbFetchAssoc($replies)) {
                    if ($reply['staffid']) {
                        $reply['name'] = $reply['staff_name'] === null ?
                            $hesklang['staff_deleted'] :
                            $reply['staff_name'];
                    } else {
                        if ($reply['customer_name'] === null || $reply['customer_name'] == '') {
                            if ($reply['customer_email'] !== null && strlen($reply['customer_email'])) {
                                $reply['name'] = $reply['customer_email'];
                            } else {
                                $reply['name'] = $hesklang['anon_name'];
                            }
                        } else {
                            $reply['name'] = $reply['customer_name'];
                        }
                    }

                    $reply['message'] = hesk_msgToPlain($reply['message'], 1, 0);

                    $tmp .= hesk_escape_CDATA(
                        $hesklang['reply_by'] . ' ' . $reply['name'] . "\n" .
                        $hesklang['date'] . ' ' . hesk_date($reply['dt'], true) . "\n" .
                        $reply['message'] . "\n---\n"
                    );
                }
            }

            $tmp .= ']]></Data></Cell>'."\n";
        }

        // Include a link to ticket
        if ($hesk_settings['email_view_ticket'] && isset($requester['email'])) {
            $tmp .= '<Cell><Data ss:Type="String">'.$hesk_settings['hesk_url'].'/ticket.php?track='.urlencode($ticket['trackid']).'&amp;e='.urlencode($requester['email']).'</Data></Cell>';
        } else {
            $tmp .= '<Cell><Data ss:Type="String">'.$hesk_settings['hesk_url'].'/ticket.php?track='.urlencode($ticket['trackid']).'</Data></Cell>';
        }
		$tmp .= "</Row>\n";

		// Write every 100 rows into the file
		if ($this_round >= $save_after)
		{
			file_put_contents($save_to, $tmp, FILE_APPEND);
			$this_round = 0;
			$tmp = '';
			usleep(1);
		}

        $tickets_exported++;
        $this_round++;
	} // End of while loop

	// Go back to the HH:MM:SS format for hesk_date()
	$hesk_settings['format_timestamp'] = 'H:i:s';

	// Append any remaining rows into the file
	if ($this_round > 0)
	{
		file_put_contents($save_to, $tmp, FILE_APPEND);
	}

	// If any tickets were exported, continue, otherwise cleanup
	if ($tickets_exported > 0)
	{
		// Finish the XML file
	    $tmp = '
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <PageSetup>
    <Header x:Margin="0.3"/>
    <Footer x:Margin="0.3"/>
    <PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/>
   </PageSetup>
   <Selected/>
   <Panes>
    <Pane>
     <Number>3</Number>
     <ActiveRow>4</ActiveRow>
    </Pane>
   </Panes>
   <ProtectObjects>False</ProtectObjects>
   <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>
 <Worksheet ss:Name="Sheet2">
  <Table ss:ExpandedColumnCount="1" ss:ExpandedRowCount="1" x:FullColumns="1"
   x:FullRows="1" ss:DefaultRowHeight="15">
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <PageSetup>
    <Header x:Margin="0.3"/>
    <Footer x:Margin="0.3"/>
    <PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/>
   </PageSetup>
   <ProtectObjects>False</ProtectObjects>
   <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>
 <Worksheet ss:Name="Sheet3">
  <Table ss:ExpandedColumnCount="1" ss:ExpandedRowCount="1" x:FullColumns="1"
   x:FullRows="1" ss:DefaultRowHeight="15">
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <PageSetup>
    <Header x:Margin="0.3"/>
    <Footer x:Margin="0.3"/>
    <PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/>
   </PageSetup>
   <ProtectObjects>False</ProtectObjects>
   <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>
</Workbook>
';
		file_put_contents($save_to, $tmp, FILE_APPEND);

		// Log how many rows we exported
		$flush_me .= hesk_date() . " | " . sprintf($hesklang['nrow'], $tickets_exported) . "<br />\n";

        return array($save_to, $tickets_exported, $export_name, $flush_me);
	}
    // No tickets exported, cleanup
    else
    {
		hesk_unlink($save_to);
    }

    return array('', $tickets_exported, $export_name, $flush_me);

} // END hesk_export_build_xml_file()


function hesk_export_zip_xml_file($save_to, $export_name)
{
    global $hesk_settings, $hesklang;

    $export_dir = hesk_export_get_cache_dir();
    $save_to_zip = $export_dir.$export_name.'.zip';

    // Log start of Zip creation
    if (extension_loaded('zip'))
    {
        $zip = new ZipArchive;
        $res = $zip->open($save_to_zip, ZipArchive::CREATE);
        if ($res === TRUE)
        {
            $zip->addFile($save_to, "{$export_name}.xml");
            $zip->close();
        }
        else
        {
            die("{$hesklang['eZIP']} <$save_to_zip>\n");
        }
    }
    // Some servers have ZipArchive class enabled anyway - can we use it?
    elseif ( class_exists('ZipArchive') )
    {
        require(HESK_PATH . 'inc/zip/Zip.php');
        $zip = new Zip();
        $zip->addLargeFile($save_to, "{$export_name}.xml");
        $zip->finalize();
        $zip->setZipFile($save_to_zip);
    }
    // If not available, use a 3rd party Zip class included with HESK
    else
    {
        require(HESK_PATH . 'inc/zip/pclzip.lib.php');
        $zip = new PclZip($save_to_zip);
        $zip->add($save_to, PCLZIP_OPT_REMOVE_ALL_PATH);
    }

    return $save_to_zip;
}


function hesk_export_get_cache_dir()
{
    global $hesk_settings;

    return HESK_PATH . $hesk_settings['cache_dir'] . '/';
}


function hesk_prepare_export_cache_dir($export_dir)
{
    global $hesklang;

    if ( is_dir($export_dir) || ( @mkdir($export_dir, 0777) && is_writable($export_dir) ) )
    {
        if ( ! file_exists($export_dir.'index.htm'))
        {
            @file_put_contents($export_dir.'index.htm', '');
        }

        hesk_purge_cache('export', 86400);

        return true;
    }

    hesk_error($hesklang['ede']);
}


function hesk_get_dashboard_sync_state()
{
    $file = hesk_get_dashboard_sync_state_file();

    if (!file_exists($file))
    {
        return array();
    }

    $contents = @file_get_contents($file);
    if ($contents === false || $contents === '')
    {
        return array();
    }

    $state = json_decode($contents, true);

    return is_array($state) ? $state : array();
}


function hesk_write_dashboard_sync_state($state)
{
    $export_dir = hesk_export_get_cache_dir();
    hesk_prepare_export_cache_dir($export_dir);

    $existing_state = hesk_get_dashboard_sync_state();
    $new_state = array_merge($existing_state, $state);

    @file_put_contents(hesk_get_dashboard_sync_state_file(), json_encode($new_state));
}


function hesk_get_dashboard_sync_state_file()
{
    return hesk_export_get_cache_dir() . 'dashboard_export_sync.json';
}


/**
 * Build a JSON-serializable payload for browser console logging after a dashboard sync attempt.
 *
 * @param array $dashboard_result Return value from hesk_export_push_to_dashboard()
 * @param string $context Logical source, e.g. bulk_export or single_ticket
 * @param array $extra Optional extra scalar fields (e.g. trackid)
 * @return array
 */
function hesk_dashboard_sync_client_log_data($dashboard_result, $context, $extra = array())
{
    $base = array(
        'context' => (string) $context,
        'success' => !empty($dashboard_result['success']),
        'tickets_exported' => isset($dashboard_result['tickets_exported']) ? (int) $dashboard_result['tickets_exported'] : 0,
        'http_code' => isset($dashboard_result['http_code']) ? (int) $dashboard_result['http_code'] : 0,
        'error_message' => isset($dashboard_result['error_message']) ? (string) $dashboard_result['error_message'] : '',
    );

    if (!is_array($extra) || !count($extra))
    {
        return $base;
    }

    foreach ($extra as $k => $v)
    {
        if (is_string($k) && (is_string($v) || is_int($v) || is_float($v) || is_bool($v)))
        {
            $base[$k] = $v;
        }
    }

    return $base;
}


/**
 * Emit a one-line script that logs dashboard sync results to the browser console.
 *
 * @param array $data From hesk_dashboard_sync_client_log_data()
 */
function hesk_dashboard_sync_emit_console_script_from_data(array $data)
{
    $json = json_encode(
        $data,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );

    if ($json === false)
    {
        return;
    }

    echo '<script>console.log("[HESK dashboard sync]", ' . $json . ');</script>' . "\n";
}


function hesk_escape_CDATA($in)
{
    return str_replace(']]>', ']]]]><![CDATA[>', $in);
} // END hesk_escape_CDATA()

function format_display_name($row) {
    if ($row['name']) {
        return $row['email'] ? "{$row['name']} <{$row['email']}>" : $row['name'];
    }

    return $row['email'];
}
