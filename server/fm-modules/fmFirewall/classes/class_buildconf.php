<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013 The facileManager Team                               |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | facileManager: Easy System Administration                               |
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

class fm_module_buildconf {
	
	/**
	 * Processes the server configs
	 *
	 * @since 1.0
	 * @package fmFirewall
	 *
	 * @param array $files_array Array containing named files and contents
	 * @return string
	 */
	function processConfigs($raw_data) {
		$preview = null;
		
		$check_status = null;
		
		foreach ($raw_data['files'] as $filename => $contents) {
			$preview .= str_repeat('=', 75) . "\n";
			$preview .= $filename . ":\n";
			$preview .= str_repeat('=', 75) . "\n";
			$preview .= $contents . "\n\n";
		}

		return array($preview, $check_status);
	}
	
	/**
	 * Generates the server config and updates the firewall server
	 *
	 * @since 1.0
	 * @package fmFirewall
	 */
	function buildServerConfig($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		/** Get datetime formatting */
		$date_format = getOption('date_format', $_SESSION['user']['account_id']);
		$time_format = getOption('time_format', $_SESSION['user']['account_id']);
		
		$files = array();
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);

		$GLOBALS['built_domain_ids'] = null;
		$data->server_build_all = true;
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$server_result = $fmdb->last_result;
			$data = $server_result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			/** Disabled DNS server */
			if ($server_status != 'active') {
				$error = "Server is $server_status.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				
				exit;
			}
			
			include(ABSPATH . 'fm-includes/version.php');
			
			$config = '# This file was built using ' . $_SESSION['module'] . ' ' . $__FM_CONFIG[$_SESSION['module']]['version'] . ' on ' . date($date_format . ' ' . $time_format . ' e') . "\n\n";

			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'policies', 'policy_order_id', 'policy_', "AND server_serial_no=$server_serial_no AND policy_status='active'");
			if ($fmdb->num_rows) {
				$policy_count = $fmdb->num_rows;
				$policy_result = $fmdb->last_result;
				
				$function = $server_type . 'BuildConfig';
				$config .= $this->$function($policy_result, $policy_count);
			}




			$data->files[$server_config_file] = $config;
			if (is_array($files)) {
				$data->files = array_merge($data->files, $files);
			}
			
//			print_r($data);
//			exit;
			
			return get_object_vars($data);
		}
		
		/** Bad server */
		$error = "Server is not found.\n";
		if ($compress) echo gzcompress(serialize($error));
		else echo serialize($error);
	}
	
	
	/**
	 * Figures out what files to update on the firewall
	 *
	 * @since 1.0
	 * @package fmFirewall
	 */
	function buildCronConfigs($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			$data = $result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			/** Check if this server is configured for cron updates */
			if ($server_update_method != 'cron') {
				$error = "This server is not configured to receive updates via cron.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				return;
			}
			
			/** Check if there are updates */
			if ($server_update_config == 'no') {
				$error = "No updates found.\n";
				if ($compress) echo gzcompress(serialize($error));
				else echo serialize($error);
				return;
			}
			
			/** process server config build */
			$config = $this->buildServerConfig($post_data);
			return $config;
			
		}
		
		/** Bad DNS server */
		$error = "DNS server is not found.\n";
		if ($compress) echo gzcompress(serialize($error));
		else echo serialize($error);
	}
	

	/**
	 * Updates tables to reset flags
	 *
	 * @since 1.0
	 * @package fmFirewall
	 */
	function updateReloadFlags($post_data) {
		global $fmdb, $__FM_CONFIG;
		
		$server_serial_no = sanitize($post_data['SERIALNO']);
		extract($post_data);
		
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
		if ($fmdb->num_rows) {
			$result = $fmdb->last_result;
			$data = $result[0];
			extract(get_object_vars($data), EXTR_SKIP);
			
			$reset_build = setBuildUpdateConfigFlag($server_serial_no, 'no', 'build');
			$reset_update = setBuildUpdateConfigFlag($server_serial_no, 'no', 'update');
//			$msg = (!setBuildUpdateConfigFlag($server_serial_no, 'no', 'build') && !setBuildUpdateConfigFlag($server_serial_no, 'no', 'update')) ? "Could not update the backend database.\n" : "Success.\n";
			$msg = "Success.\n";
		} else $msg = "Server is not found.\n";
		
		if ($compress) echo gzcompress(serialize($msg));
		else echo serialize($msg);
	}
	
	
	function iptablesBuildConfig($policy_result, $count) {
		global $fmdb, $__FM_CONFIG;
		
		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_time.php');
		
		$fw_actions = array('pass' => 'ACCEPT',
							'block' => 'DROP',
							'reject' => 'REJECT');
		
		$config[] = '*filter';
		$config[] = ':INPUT ACCEPT [0:0]';
		$config[] = ':FORWARD ACCEPT [0:0]';
		$config[] = ':OUTPUT ACCEPT [0:0]';
		$config[] = '';
		
		for ($i=0; $i<$count; $i++) {
			$line = null;
			$log_rule = false;
			
			$rule_title = 'fmFirewall Rule ' . $policy_result[$i]->policy_order_id;
			$config[] = '# ' . $rule_title;
			$config[] = wordwrap('# ' . $policy_result[$i]->policy_comment, 20, "\n");
			
			if ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['log']) {
				$log_rule = true;
				$log_chain = 'RULE_' . $policy_result[$i]->policy_order_id;
				$config[] = '-N ' . $log_chain;
				$config[] = '-A ' . strtoupper($policy_result[$i]->policy_direction) . 'PUT -j ' . $log_chain;
			}
			
			$rule_chain = $log_rule ? $log_chain : $fw_actions[$policy_result[$i]->policy_action];
			
			$line[] = '-A';
			$line[] = strtoupper($policy_result[$i]->policy_direction) . 'PUT';
			if ($policy_result[$i]->policy_interface != 'any') {
				if ($policy_result[$i]->policy_direction == 'in') {
					$line[] = '-i ' . $policy_result[$i]->policy_interface;
				} elseif ($policy_result[$i]->policy_direction == 'out') {
					$line[] = '-o ' . $policy_result[$i]->policy_interface;
				}
			}
			
			
			/** Handle sources */
			unset($policy_source);
			if ($temp_source = trim($policy_result[$i]->policy_source, ';')) {
				$policy_source = $this->buildAddressList($temp_source);
			} else $policy_source[] = null;
			
			
			/** Handle destinations */
			unset($policy_destination);
			if ($temp_destination = trim($policy_result[$i]->policy_destination, ';')) {
				$policy_destination = $this->buildAddressList($temp_destination);
			} else $policy_destination[] = null;
			
			
			/** Handle match inverses */
			$source_not = ($policy_result[$i]->policy_source_not) ? '! ' : null;
			$destination_not = ($policy_result[$i]->policy_destination_not) ? '! ' : null;
			$services_not = ($policy_result[$i]->policy_services_not) ? '! ' : null;

			/** Handle services */
			$tcp = $udp = $icmp = null;
			if ($assigned_services = trim($policy_result[$i]->policy_services, ';')) {
				foreach (explode(';', $assigned_services) as $temp_id) {
					$temp_services = null;
					if ($temp_id[0] == 'g') {
						$temp_services[] = $this->extractItemsFromGroup($temp_id);
					} else {
						$temp_services[] = substr($temp_id, 1);
					}
					
					if (is_array($temp_services[0])) $temp_services = $temp_services[0];
					
					foreach ($temp_services as $service_id) {
						basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $service_id, 'service_', 'service_id', 'active');
						$result = $fmdb->last_result[0];
						
						if ($result->service_type == 'icmp') {
							$icmp_type = $result->service_icmp_type;
							if ($result->service_icmp_code > -1) $icmp_type .= '/' . $result->service_icmp_code;
							
							$policy_services['processed'][$result->service_type][] = ' -p icmp -m icmp --icmp-type ' . $icmp_type;
						} else {
							/** Source ports */
							@list($start, $end) = explode(':', $result->service_src_ports);
							if ($start && $end) {
								$service_source = ($start == $end) ? $start : $result->service_src_ports;
							} else $service_source = null;
							
							/** Destination ports */
							@list($start, $end) = explode(':', $result->service_dest_ports);
							if ($start && $end) {
								$service_destination = ($start == $end) ? $start : $result->service_dest_ports;
							} else $service_destination = null;
							
							/** Determine which array to put the service in */
							if ($service_source && $service_destination) {
								$policy_services[$result->service_type]['s-d']['s'][] = $service_source;
								$policy_services[$result->service_type]['s-d']['d'][] = $service_destination;
							} elseif ($service_source && !$service_destination) {
								$policy_services[$result->service_type]['s-any']['s'][] = $service_source;
							} elseif (!$service_source && $service_destination) {
								$policy_services[$result->service_type]['any-d']['d'][] = $service_destination;
							}
						}
					}
				}
			}
			
			if (@is_array($policy_services)) {
				foreach ($policy_services as $protocol => $proto_array) {
					if ($protocol == 'processed') continue;
					
					foreach ($proto_array as $direction_group => $group_array) {
						foreach ($group_array as $direction => $port_array) {
							$l = $k = $j = 0;
							foreach ($port_array as $port) {
								if ($l) break;
								
								if ($j > 14) {
									$k++;
									$j = 0;
								}
								
								if ($direction_group == 's-d') {
									if (@array_key_exists($l, $group_array['s'])) {
										$multiports[$k][] = $group_array['s'][$l] . ' --dport ' . $group_array['d'][$l];
										unset($group_array);
									}
									$l++;
								} else {
									$multiports[$k][] = $port;
								}
								if (strpos($port, ':')) $j++;
								
								$j++;
							}
							if (@is_array($multiports)) {
								foreach ($multiports as $ports) {
									$ports = array_unique($ports);
									$multi = (count($ports) > 1) ? ' -m multiport --' . $direction . 'ports ' : ' --' . $direction . 'port ';
									$policy_services['processed'][$protocol][] = ' -p ' . $protocol . $multi . $services_not . implode(',', $ports);
								}
							}
							unset($multiports);
						}
					}
					unset($policy_services[$protocol]);
				}
			}
			
			/** Handle time restrictions */
			$time_restrictions = null;
			if ($policy_result[$i]->policy_time) {
				basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'time', $policy_result[$i]->policy_time, 'time_', 'time_id', 'active');
				if ($fmdb->num_rows) {
					$time = null;
					$time_result = $fmdb->last_result[0];
					
					if ($time_result->time_start_date) $time[] = '--datestart ' . date('Y:m:d', strtotime($time_result->time_start_date));
					if ($time_result->time_end_date) $time[] = '--datestop ' . date('Y:m:d', strtotime($time_result->time_end_date));
					
					if ($time_result->time_start_time) $time[] = '--timestart ' . $time_result->time_start_time;
					if ($time_result->time_end_time) $time[] = '--timestop ' . $time_result->time_end_time;
					
					if ($time_result->time_weekdays && $time_result->time_weekdays != array_sum($__FM_CONFIG['weekdays'])) {
						$time[] = '--days ' . str_replace(' ', '', $fm_module_time->formatDays($time_result->time_weekdays));
					}
					
					$time_restrictions = implode(' ', $time);
				}
				
				$line[] = $time_restrictions;
			}
			
			@sort($policy_services['processed']);
			
			/** Build the rules */
			foreach ($policy_source as $source_address) {
				$source = ($source_address) ? ' -s ' . $source_not . $source_address : null;
				foreach ($policy_destination as $destination_address) {
					$destination = ($destination_address) ? ' -d ' . $destination_not . $destination_address : null;
					if (is_array($policy_services['processed'])) {
						foreach ($policy_services['processed'] as $line_array) {
							foreach ($line_array as $rule) {
								$config[] = implode(' ', $line) . $source . $destination . $rule . ' -j ' . $fw_actions[$policy_result[$i]->policy_action];
							}
						}
					} else {
						$config[] = implode(' ', $line) . $source . $destination . ' -j ' . $rule_chain;
					}
				}
			}
			unset($policy_services['processed']);
			
			/** Handle logging */
			if ($log_rule) {
				$config[] = '-A ' . $log_chain . ' -j LOG --log-level info --log-prefix "' . $rule_title . ' - ' . strtoupper($policy_result[$i]->policy_action) . ':"';
				$config[] = '-A ' . $log_chain . ' -j ' . $fw_actions[$policy_result[$i]->policy_action];
			}
			
			$config[] = null;
		}
		
		$config[] = 'COMMIT';
		
		return implode("\n", $config);
	}
	
	
	function pfBuildConfig($policy_result, $count) {
		echo '<pre>';
		echo "pf\n";
		print_r($policy_result);
	}
	
	
	function ipfilterBuildConfig($policy_result, $count) {
		global $fmdb, $__FM_CONFIG;
		
		$fw_actions = array('pass' => 'pass',
							'block' => 'block',
							'reject' => 'block');
		
		for ($i=0; $i<$count; $i++) {
			$line = null;
			
			$rule_title = 'fmFirewall Rule ' . $policy_result[$i]->policy_order_id;
			$config[] = '# ' . $rule_title;
			$config[] = wordwrap('# ' . $policy_result[$i]->policy_comment, 20, "\n");
			
			$line[] = $fw_actions[$policy_result[$i]->policy_action];
			$line[] = $policy_result[$i]->policy_direction;
			
			/** Handle logging */
			if ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['log']) {
				$line[] = 'log';
			}
			
			$line[] = 'quick';

			/** Handle interface */
			$interface = ($policy_result[$i]->policy_interface != 'any') ? 'on ' . $policy_result[$i]->policy_interface : null;
			if ($interface) $line[] = $interface;
			
			/** Handle keep-states */
			$keep_state = ($policy_result[$i]->policy_action == 'pass') ? ' keep state' : null;

			/** Handle sources */
			unset($policy_source);
			if ($temp_source = trim($policy_result[$i]->policy_source, ';')) {
				$policy_source = $this->buildAddressList($temp_source);
			} else $policy_source[] = 'any';
			
			
			/** Handle destinations */
			unset($policy_destination);
			if ($temp_destination = trim($policy_result[$i]->policy_destination, ';')) {
				$policy_destination = $this->buildAddressList($temp_destination);
			} else $policy_destination[] = 'any';
			
			
			/** Handle services */
			$services_not = ($policy_result[$i]->policy_services_not) ? '!' : null;
			$tcp = $udp = $icmp = null;
			if ($assigned_services = trim($policy_result[$i]->policy_services, ';')) {
				foreach (explode(';', $assigned_services) as $temp_id) {
					$temp_services = null;
					if ($temp_id[0] == 'g') {
						$temp_services[] = $this->extractItemsFromGroup($temp_id);
					} else {
						$temp_services[] = substr($temp_id, 1);
					}
					
					if (is_array($temp_services[0])) $temp_services = $temp_services[0];
					
					foreach ($temp_services as $service_id) {
						basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $service_id, 'service_', 'service_id', 'active');
						$result = $fmdb->last_result[0];
						
						if ($result->service_type == 'icmp') {
							$policy_services[$result->service_type][] = $result->service_icmp_type;
						} else {
							/** Source ports */
							@list($start, $end) = explode(':', $result->service_src_ports);
							if ($start && $end) {
								$service_source = ($start == $end) ? $start : str_replace(':', ' <> ', $result->service_src_ports);
							} else $service_source = null;
							
							/** Destination ports */
							@list($start, $end) = explode(':', $result->service_dest_ports);
							if ($start && $end) {
								$service_destination = ($start == $end) ? $start : str_replace(':', ' <> ', $result->service_dest_ports);
							} else $service_destination = null;
							
							/** Determine which array to put the service in */
							if ($service_source && $service_destination) {
								$policy_services[$result->service_type]['s-d']['s'][] = $service_source;
								$policy_services[$result->service_type]['s-d']['d'][] = $service_destination;
							} elseif ($service_source && !$service_destination) {
								$policy_services[$result->service_type]['s-any']['s'][] = $service_source;
							} elseif (!$service_source && $service_destination) {
								$policy_services[$result->service_type]['any-d']['d'][] = $service_destination;
							}
						}
					}
				}
			}
			
			/** Build the rules */
			foreach ($policy_source as $source_address) {
				$source = ($source_address) ? ' from ' . $source_address : null;
				foreach ($policy_destination as $destination_address) {
					$destination = ($destination_address) ? ' to ' . $destination_address : null;
					if (@is_array($policy_services)) {
						foreach ($policy_services as $protocol => $proto_array) {
							if ($protocol == 'icmp') {
								foreach (@array_unique($proto_array) as $type) {
									$config[] = implode(' ', $line) . " proto $protocol" . $source . $destination . ' icmp-type ' . $type . $keep_state;
								}
							} else {
								foreach ($proto_array as $direction_group => $direction_array) {
									$source_port = $destination_port = null;
									if ($direction_group == 's-any') {
										$source_port = ' port ' . $services_not . '= ';
										foreach (@array_unique($direction_array['s']) as $port) {
											$config[] = implode(' ', $line) . " proto $protocol" . $source . $source_port . $port . $destination . $destination_port . $keep_state;
										}
									} elseif ($direction_group == 'any-d') {
										$destination_port = ' port ' . $services_not . '= ';
										foreach (@array_unique($direction_array['d']) as $port) {
											$config[] = implode(' ', $line) . " proto $protocol" . $source . $source_port . $destination . $destination_port . $port . $keep_state;
										}
									} elseif ($direction_group == 's-d') {
										$source_port = ' port ' . $services_not . '= ';
										$destination_port = ' port ' . $services_not . '= ';
										foreach (@array_unique($direction_array['s']) as $index => $port) {
											$config[] = implode(' ', $line) . " proto $protocol" . $source . $source_port . $port . $destination . $destination_port . $direction_array['d'][$index] . $keep_state;
										}
									}
								}
							}
						}
					} else {
						$config[] = implode(' ', $line) . $source . $destination;
					}
				}
			}
			unset($policy_services);
			

			$config[] = null;
		}

		return implode("\n", $config);
	}
	
	
	function ipfwBuildConfig($policy_result, $count) {
		global $fmdb, $__FM_CONFIG;
		
		$fw_actions = array('pass' => 'allow',
							'block' => 'deny',
							'reject' => 'unreach host');
		
		$cmd = 'ipfw -q add';
		
		$config[] = 'ipfw -q -f flush';
		$config[] = $cmd . ' check-state';
		$config[] = null;
		
		for ($i=0; $i<$count; $i++) {
			$line = null;
			
			$rule_title = 'fmFirewall Rule ' . $policy_result[$i]->policy_order_id;
			$config[] = '# ' . $rule_title;
			$config[] = wordwrap('# ' . $policy_result[$i]->policy_comment, 20, "\n");
			
			$line[] = $cmd;
			$line[] = $fw_actions[$policy_result[$i]->policy_action];
			
			/** Handle logging */
			if ($policy_result[$i]->policy_options & $__FM_CONFIG['fw']['policy_options']['log']) {
				$line[] = 'log';
			}
			
			/** Handle interface */
			$interface = ($policy_result[$i]->policy_interface != 'any') ? ' via ' . $policy_result[$i]->policy_interface : null;
			
			/** Handle keep-states */
			$keep_state = ($policy_result[$i]->policy_action == 'pass') ? ' keep-state' : null;

			/** Handle match inverses */
			$services_not = ($policy_result[$i]->policy_services_not) ? 'not' : null;

			/** Handle sources */
			unset($policy_source);
			if ($temp_source = trim($policy_result[$i]->policy_source, ';')) {
				$policy_source = $this->buildAddressList($temp_source);
			} else $policy_source = null;
			$source_address = ($policy_result[$i]->policy_source_not) ? 'not ' : null;
			$source_address .= (is_array($policy_source)) ? implode(',', $policy_source) : 'any';
			
			/** Handle destinations */
			unset($policy_destination);
			if ($temp_destination = trim($policy_result[$i]->policy_destination, ';')) {
				$policy_destination = $this->buildAddressList($temp_destination);
			} else $policy_destination = null;
			$destination_address = ($policy_result[$i]->policy_destination_not) ? 'not ' : null;
			$destination_address .= (is_array($policy_destination)) ? implode(',', $policy_destination) : 'any';
			
			/** Handle services */
			$tcp = $udp = $icmp = null;
			if ($assigned_services = trim($policy_result[$i]->policy_services, ';')) {
				foreach (explode(';', $assigned_services) as $temp_id) {
					$temp_services = null;
					if ($temp_id[0] == 'g') {
						$temp_services[] = $this->extractItemsFromGroup($temp_id);
					} else {
						$temp_services[] = substr($temp_id, 1);
					}
					
					if (is_array($temp_services[0])) $temp_services = $temp_services[0];
					
					foreach ($temp_services as $service_id) {
						basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $service_id, 'service_', 'service_id', 'active');
						$result = $fmdb->last_result[0];
						
						if ($result->service_type == 'icmp') {
							$policy_services[$result->service_type][] = $result->service_icmp_type;
						} else {
							/** Source ports */
							@list($start, $end) = explode(':', $result->service_src_ports);
							if ($start && $end) {
								$service_source = ($start == $end) ? $start : str_replace(':', '-', $result->service_src_ports);
							} else $service_source = null;
							
							/** Destination ports */
							@list($start, $end) = explode(':', $result->service_dest_ports);
							if ($start && $end) {
								$service_destination = ($start == $end) ? $start : str_replace(':', '-', $result->service_dest_ports);
							} else $service_destination = null;
							
							/** Determine which array to put the service in */
							if ($service_source && $service_destination) {
								$policy_services[$result->service_type]['s-d']['s'][] = $service_source;
								$policy_services[$result->service_type]['s-d']['d'][] = $service_destination;
							} elseif ($service_source && !$service_destination) {
								$policy_services[$result->service_type]['s-any']['s'][] = $service_source;
							} elseif (!$service_source && $service_destination) {
								$policy_services[$result->service_type]['any-d']['d'][] = $service_destination;
							}
						}
					}
				}
			}
			
			/** Build the rules */
			if (@is_array($policy_services)) {
				foreach ($policy_services as $protocol => $proto_array) {
					foreach ($proto_array as $direction_group => $direction_array) {
						$source_ports = $destination_ports = null;
						if ($direction_group == 's-any') {
							$source_ports = $services_not . ' ' . @implode(',', @array_unique($direction_array['s']));
						} elseif ($direction_group == 'any-d') {
							$destination_ports = $services_not . ' ' . @implode(',', @array_unique($direction_array['d']));
						} elseif ($direction_group == 's-d') {
							$source_ports = $services_not . ' ' . implode(',', array_unique($direction_array['s']));
							$destination_ports = $services_not . ' ' . implode(',', array_unique($direction_array['d']));
						}
						$icmptypes = ($protocol == 'icmp') ? ' icmptypes ' . $services_not . ' ' . implode(',', $proto_array) : null;
		
						$config[] = implode(' ', $line) . " $protocol from " . $source_address . $source_ports . ' to ' . $destination_address . $destination_ports . $icmptypes . ' ' . $policy_result[$i]->policy_direction . $interface . $keep_state;
					}
				}
				unset($policy_services);
			} else {
				$config[] = implode(' ', $line) . " all from $source_address to $destination_address " . $policy_result[$i]->policy_direction . $interface . $keep_state;
			}
			
			$config[] = null;
		}
		
		return implode("\n", $config);
	}
	
	
	function extractItemsFromGroup($group_id) {
		global $fmdb, $__FM_CONFIG;
		
		$new_group_items = null;
		
		if ($group_id[0] == 'g') {
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', substr($group_id, 1), 'group_', 'group_id', 'active');
			$group_result = $fmdb->last_result[0];
			$group_items = $group_result->group_items;
			
			foreach (explode(';', trim($group_result->group_items, ';')) as $id) {
				if ($id[0] == 'g') {
					$new_group_items = $this->extractItemsFromGroup($id);
				} else {
					$temp_items[] = substr($id, 1);
				}
			}
		} else {
			$temp_items[] = substr($group_id, 1);
		}
		
		if (is_array($new_group_items)) $temp_items = array_merge($temp_items, $new_group_items);
		
		return $temp_items;
	}
	
	
	function buildAddressList($addresses) {
		global $fmdb, $__FM_CONFIG;
		
		$address_list = null;
		
		$address_ids = explode(';', $addresses);
		foreach ($address_ids as $temp_id) {
			$temp = null;
			if ($temp_id[0] == 'g') {
				$temp[] = $this->extractItemsFromGroup($temp_id);
			} else {
				$temp[] = substr($temp_id, 1);
			}
			
			if (is_array($temp[0])) $temp = $temp[0];
			
			foreach ($temp as $object_id) {
				basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', $object_id, 'object_', 'object_id', 'active');
				$result = $fmdb->last_result[0];
				
				if ($result->object_type == 'network') {
					$address_list[] = $result->object_address . '/' . $this->mask2cidr($result->object_mask);
				} else {
					$address_list[] = $result->object_address;
				}
			}
		}
		
		return $address_list;
	}
	
	
	function mask2cidr($mask) {
		$long = ip2long($mask);
		$base = ip2long('255.255.255.255');
		return 32 - log(($long ^ $base) +1, 2);
	}
	
	
	/**
	 * Updates the daemon version number in the database
	 *
	 * @since 1.0
	 * @package fmFirewall
	 */
	function updateServerVersion() {
		global $fmdb, $__FM_CONFIG;
		
		$query = "UPDATE `fm_{$__FM_CONFIG['fmFirewall']['prefix']}servers` SET `server_version`='" . $_POST['server_version'] . "', `server_os`='" . $_POST['server_os'] . "' WHERE `server_serial_no`='" . $_POST['SERIALNO'] . "' AND `account_id`=
			(SELECT account_id FROM `fm_accounts` WHERE `account_key`='" . $_POST['AUTHKEY'] . "')";
		$fmdb->query($query);
	}
	
	
	/**
	 * Validate the daemon version number of the client
	 *
	 * @since 1.0
	 * @package fmFirewall
	 */
	function validateDaemonVersion($data) {
		global $__FM_CONFIG;
		extract($data);
		
		return true;
		
		if ($server_type == 'bind9') {
			$required_version = $__FM_CONFIG['fmFirewall']['required_dns_version'];
		}
		
		if (version_compare($server_version, $required_version, '<')) {
			return false;
		}
		
		return true;
	}


}

if (!isset($fm_module_buildconf))
	$fm_module_buildconf = new fm_module_buildconf();

?>