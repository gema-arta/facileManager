<?php

class fm_module_servers {
	
	/**
	 * Displays the server list
	 */
	function rows($result) {
		global $fmdb;
		
		echo '			<table class="display_results" id="table_edits" name="servers">' . "\n";
		if (!$result) {
			echo '<p id="noresult">There are no servers defined.</p>';
		} else {
			echo <<<HEAD
				<thead>
					<tr>
						<th>Hostname</th>
						<th>Type</th>
						<th>Groups</th>
						<th width="110" style="text-align: center;">Actions</th>
					</tr>
				</thead>
HEAD;

			echo "<tbody>\n";

			$num_rows = $fmdb->num_rows;
			$results = $fmdb->last_result;
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x]);
			}

			echo "</tbody>\n";
		}
		echo '			</table>' . "\n";
	}

	/**
	 * Adds the new server
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;
		
		extract($post, EXTR_SKIP);
		
		$server_name = sanitize($server_name);
		
		if (empty($server_name)) return 'No server name defined.';
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_name');
		if ($field_length !== false && strlen($server_name) > $field_length) return 'Server name is too long (maximum ' . $field_length . ' characters).';
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', $server_name, 'server_', 'server_name');
		if ($fmdb->num_rows) return 'This server name already exists.';
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers`";
		$sql_fields = '(';
		$sql_values = null;
		
		$log_message = "Added a database server with the following details:\n";

		$post['account_id'] = $_SESSION['user']['account_id'];
		
		/** Set default ports */
		if (!empty($post['server_port']) && !verifyNumber($post['server_port'], 1, 65535, false)) return 'Server port must be a valid TCP port.';
		if (empty($post['server_port'])) {
			$post['server_port'] = $__FM_CONFIG['fmSQLPass']['default']['ports'][$post['server_type']];
		}
		
		$module = isset($post['module_name']) ? $post['module_name'] : $_SESSION['module'];

		/** Get a valid and unique serial number */
		$post['server_serial_no'] = (isset($post['server_serial_no'])) ? $post['server_serial_no'] : generateSerialNo($module);

		$exclude = array('submit', 'action', 'server_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config');

		/** Convert groups and policies arrays into strings */
		if (isset($post['server_groups']) && is_array($post['server_groups'])) {
			$temp_var = null;
			foreach ($post['server_groups'] as $id) {
				$temp_var .= $id . ';';
			}
			$post['server_groups'] = rtrim($temp_var, ';');
		}
		
		/** Handle credentials */
		if (is_array($post['server_credentials'])) {
			$post['server_credentials'] = serialize($post['server_credentials']);
		}
		
		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
				if ($key == 'server_credentials') {
					$clean_data = str_repeat('*', 7);
				}
				if ($key == 'server_groups') {
					if ($post['server_groups']) {
						$group_array = explode(';', $post['server_group']);
						$clean_data = null;
						foreach ($group_array as $group_id) {
							$clean_data .= getNameFromID($group_id, 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_', 'group_id', 'group_name') . '; ';
						}
						$clean_data = rtrim($clean_data, '; ');
					} else $clean_data = 'None';
				}
				$log_message .= ($clean_data && $key != 'account_id') ? ucwords(str_replace('_', ' ', str_replace('server_', '', $key))) . ": $clean_data\n" : null;
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$result) return 'Could not add the server because a database error occurred.';

		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected server
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['server_name'])) return 'No server name defined.';

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_name');

		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', sanitize($post['server_name']), 'server_', 'server_name');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			if ($result[0]->server_id != $post['server_id']) return 'This server name already exists.';
		}
		
		/** Set default ports */
		if (!empty($post['server_port']) && !verifyNumber($post['server_port'], 1, 65535, false)) return 'Server port must be a valid TCP port.';
		if (empty($post['server_port'])) {
			$post['server_port'] = $__FM_CONFIG['fmSQLPass']['default']['ports'][$post['server_type']];
		}
		
		$exclude = array('submit', 'action', 'server_id', 'page');

		$sql_edit = null;
		
		$old_name = getNameFromID($post['server_id'], 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		$log_message = "Updated a database server ($old_name) with the following details:\n";
		
		/** Convert groups and policies arrays into strings */
		if (isset($post['server_groups']) && is_array($post['server_groups'])) {
			$temp_var = null;
			foreach ($post['server_groups'] as $id) {
				$temp_var .= $id . ';';
			}
			$post['server_groups'] = rtrim($temp_var, ';');
		}
		
		/** Handle credentials */
		if (is_array($post['server_credentials'])) {
			$post['server_credentials'] = serialize($post['server_credentials']);
		}
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
				if ($key == 'server_credentials') {
					$data = str_repeat('*', 7);
				}
				if ($key == 'server_groups') {
					if ($data) {
						$group_array = explode(';', $data);
						$clean_data = null;
						foreach ($group_array as $group_id) {
							$clean_data .= getNameFromID($group_id, 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_', 'group_id', 'group_name') . '; ';
						}
						$data = rtrim($clean_data, '; ');
					} else $data = 'None';
				}
				$log_message .= $data ? ucwords(str_replace('_', ' ', str_replace('server_', '', $key))) . ": $data\n" : null;
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		// Update the server
		$query = "UPDATE `fm_{$__FM_CONFIG['fmSQLPass']['prefix']}servers` SET $sql WHERE `server_id`={$post['server_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$result) return 'Could not add the server because a database error occurred.';

		addLogEntry($log_message);
		return $result;
	}
	
	
	/**
	 * Deletes the selected server
	 */
	function delete($id) {
		global $fmdb, $__FM_CONFIG;
		
		// Delete corresponding configs
//		if (!updateStatus('fm_config', $id, 'cfg_', 'deleted', 'cfg_server')) {
//			return 'This backup server could not be deleted.'. "\n";
//		}
		
		// Delete server
		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		if (!updateStatus('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', $id, 'server_', 'deleted', 'server_id')) {
			return 'This database server could not be deleted.'. "\n";
		} else {
			addLogEntry("Deleted database server '$tmp_name'.");
			return true;
		}
	}


	function displayRow($row) {
		global $__FM_CONFIG, $allowed_to_manage_servers, $fm_sqlpass_backup_jobs;
		
		$timezone = date("T");
		
		if ($allowed_to_manage_servers) {
			$edit_status = '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			$edit_status .= '<a href="' . $GLOBALS['basename'] . '?action=edit&id=' . $row->server_id . '&status=';
			$edit_status .= ($row->server_status == 'active') ? 'disabled' : 'active';
			$edit_status .= $row->server_serial_no ? '&server_serial_no=' . $row->server_serial_no : null;
			$edit_status .= '">';
			$edit_status .= ($row->server_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
			$edit_status .= '</a>';
			$edit_status .= '<a href="' . $GLOBALS['basename'] . '?action=delete&id=' . $row->server_id;
			$edit_status .= $row->server_serial_no ? '&server_serial_no=' . $row->server_serial_no : null;
			$edit_status .= '" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
		} else {
			$edit_status = '<p style="text-align: center;">N/A</p>';
		}
		
		/** Get some options */
		$server_backup_credentials = getServerCredentials($_SESSION['user']['account_id'], $row->server_serial_no);
		if (!empty($server_backup_credentials[0])) {
			list($backup_username, $backup_password) = $server_backup_credentials;
		} else {
			$backup_username = getOption('backup_username', $_SESSION['user']['account_id'], 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'options');
			$backup_password = getOption('backup_password', $_SESSION['user']['account_id'], 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'options');
		}
		
		/** Get group associations */
		$groups_array = explode(';', $row->server_groups);
		$groups = null;
		foreach ($groups_array as $group_id) {
			$group_name = getNameFromID($group_id, 'fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
			$groups .= "$group_name\n";
		}
		$groups = nl2br(trim($groups));
		
		if (empty($groups)) $groups = 'None';

		echo <<<HTML
		<tr id="$row->server_id">
			<td>{$row->server_name}</td>
			<td>{$row->server_type} (tcp/{$row->server_port})</td>
			<td>$groups</td>
			<td id="edit_delete_img">$edit_status</td>
		</tr>
HTML;
	}

	/**
	 * Displays the form to add new server
	 */
	function printForm($data = '', $action = 'add') {
		global $fmdb, $__FM_CONFIG;
		
		$server_id = 0;
		$server_name = $server_groups = $server_type = $server_port = null;
		$server_cred_user = $server_cred_password = $server_credentials = null;
		$server_type = 'database';
		$ucaction = ucfirst($action);
		
		/** Build groups options */
		basicGetList('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'groups', 'group_name', 'group_');
		$group_options = null;
		$group_count = $fmdb->num_rows;
		$group_results = $fmdb->last_result;
		for ($i=0; $i<$group_count; $i++) {
			$group_options[$i][] = $group_results[$i]->group_name;
			$group_options[$i][] = $group_results[$i]->group_id;
		}
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($data))
				extract($data);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		/** Check name field length */
		$server_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_name');

		$server_types = buildSelect('server_type', 'server_type', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmSQLPass']['prefix'] . 'servers', 'server_type'), $server_type);
		$groups = (is_array($group_options)) ? buildSelect('server_groups', 1, $group_options, $server_groups, 4, null, true) : 'Server Groups need to be defined first.';
		
		/** Handle credentials */
		if (isSerialized($server_credentials)) {
			$server_credentials = unserialize($server_credentials);
			list($server_cred_user, $server_cred_password) = $server_credentials;
			unset($server_credentials);
		}
		
		$return_form = <<<FORM
		<form name="manage" id="manage" method="post" action="config-servers">
			<input type="hidden" name="action" id="action" value="$action" />
			<input type="hidden" name="server_type" id="server_type" value="$server_type" />
			<input type="hidden" name="server_id" id="server_id" value="$server_id" />
			<table class="form-table">
				<tr>
					<th width="33%" scope="row"><label for="server_name">Hostname</label></th>
					<td width="67%"><input name="server_name" id="server_name" type="text" value="$server_name" size="40" maxlength="$server_name_length" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_type">Server Type</label></th>
					<td width="67%">$server_types</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_port">Server Port</label></th>
					<td width="67%"><input type="number" name="server_port" value="$server_port" placeholder="3306" onkeydown="return validateNumber(event)" maxlength="5" max="65535" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_groups">Groups</label></th>
					<td width="67%">$groups</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_cred_user">Username</label></th>
					<td width="67%"><input name="server_credentials[]" id="server_cred_user" type="text" value="$server_cred_user" size="40" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_cred_password">Password</label></th>
					<td width="67%"><input name="server_credentials[]" id="server_cred_password" type="password" value="$server_cred_password" size="40" /></td>
				</tr>
			</table>
			<input type="submit" name="submit" id="submit" value="$ucaction Server" class="button" />
			<input value="Cancel" class="button cancel" id="cancel_button" />
		</form>
FORM;

		return $return_form;
	}
	
}

if (!isset($fm_module_servers))
	$fm_module_servers = new fm_module_servers();

?>