<?php
/**
 * Two Kings FormBuilder class, for easy configuration of forms
 *
 * For more information, read: http://twokings.eu/tools/
 *
 * Two Kings Form Class and all its parts are licensed under the GPL version 2.
 * see: http://www.twokings.eu/tools/license for more information.
 *
 * @version 0.26
 * @author Lodewijk evers, lodewijk@twokings.nl
 * @copyright GPL, version 2
 * @link http://twokings.eu/tools/
 *
 * Date: 2011-01-07
 *
 */

class FormBuilder
{

	public function __construct($config=false)
	{
		global $PIVOTX;

		$this->config = $config;
		$this->basedir = dirname(__FILE__);
		// load formclass override
		if(!class_exists('FormOverride')) {
			$this->overridesloaded = $this->find_overrides();
		} else {
			$this->overridesloaded = true;
		}

		// load swiftmailer
		if(!class_exists('Swift_Mailer')) {
			$this->swiftmailerloaded = $this->find_swiftmailerclass();
		} else {
			$this->swiftmailerloaded = true;
		}

		// init form
		$this->form = new FormOverride($config['name'], $config['action']);

		// load html overrides
		if(file_exists($this->basedir.'/overrides/'.$config['templates']['elements'])) {
			include($this->basedir.'/overrides/'.$config['templates']['elements']);
		}

		// fix form variables and assign templates
		$this->form->name = (!empty($config['name']))?$config['name']:"default-form";
		$this->form->id = (!empty($config['id']))?$config['id']:"default-form";
		$this->form->spamkey = $PIVOTX['config']->data['server_spam_key'];
		$this->form->action = (!empty($config['action']))?$config['action']:$_SERVER["REQUEST_URI"];
		$this->form->encoding = (!empty($config['encoding'])&&$config['encoding']=='multipart/form-data')?' enctype="multipart/form-data" ': ' ';
		$this->form->method = $config['method'];
		$this->form->html = $this->html;
		$this->form->haserror = $this->haserror;
		$this->form->error = $this->error;
		$this->form->isrequired = $this->isrequired;

		return $this;
	}

	function find_overrides() {
		$loaded = false;
		if (file_exists($this->basedir.'/overrides/formclass_override.php')) {
			include_once($this->basedir.'/overrides/formclass_override.php');
			$loaded = true;
		}
		return $loaded;
	}

	function find_templates($templatename) {
		global $PIVOTX;

		// TODO: add entry or page template case
		if($PIVOTX['paths'] && !empty($PIVOTX['paths']['template_path']) && file_exists($PIVOTX['paths']['template_path'] .$templatename)) {
			// TODO: check why this doesn't work in all situations
			// try template dir
			$template = file_get_contents($PIVOTX['paths']['template_path'] .$templatename);
			debug('$template in PivotX templates initialized: ' . $templatename);
		} elseif($PIVOTX['template'] && !empty($PIVOTX['template']->template_dir) && file_exists($PIVOTX['template']->template_dir .$templatename)) {
			// TODO: check why this doesn't work in all situations
			// try current theme template dir
			$template = file_get_contents($PIVOTX['template']->template_dir .$templatename);
			debug('$template in PivotX templates theme dir initialized: ' . $templatename);
		} elseif(file_exists($this->basedir.'/templates/'.$templatename)) {
			// fallback in extension template dir
			$template = file_get_contents($this->basedir.'/templates/'.$templatename);
			debug('$template in extension dir initialized: ' . $templatename);
		} else {
			$template = $templatename;
		 	debug($template.' worst case fallback');
		}
		return $template;
	}

	function find_swiftmailerclass() {
		$loaded = false;
		if(file_exists($this->basedir.'/swiftmailer/lib/swift_required.php')) {
			include_once($this->basedir.'/swiftmailer/lib/swift_required.php');
			$loaded = true;
		} elseif (file_exists($this->basedir.'/../swiftmailer/lib/swift_required.php')) {
			include_once($this->basedir.'/../swiftmailer/lib/swift_required.php');
			$loaded = true;
		}
		return $loaded;
	}

	public function safe_string($string) {
		$string = trim(htmlentities(strip_tags($string)));
		return $string;
	}

	function send_message() {
		// debug('entering send_message');
		if ($this->swiftmailerloaded ) {

			//debug('swiftmailerloaded');
			$mail_config = $this->config['mail_config'];

			switch($mail_config['method']) {
				case 'smtp':
					//Create the Transport
					$transport = Swift_SmtpTransport::newInstance($mail_config['smtp']['server'], 25)
						->setUsername($mail_config['smtp']['login'])
						->setPassword($mail_config['smtp']['password']);
					break;
				case 'sendmail':
					//Sendmail
					$transport = Swift_SendmailTransport::newInstance('/usr/sbin/sendmail -bs');
					break;
				case 'mail':
				default:
					//Mail
					$transport = Swift_MailTransport::newInstance();
			}
			// hide exposed critical information
			$mail_config['smtp']['password'] = $form['mail_config']['smtp']['password'] = '***';

			//Create the Mailer using your created Transport
			$mailer = Swift_Mailer::newInstance($transport);

			//Create a message
			if(empty($mail_config['subject'])) {
				$subject = sprintf(ft('Form submission by: %s'), $this->config['name']);
			} else {
				$subject = $mail_config['subject'];
			}

			// add mail addresses from form name and email values
			// or use the default configured settings
			foreach(array('recipient', 'sender', 'cc', 'bcc') as $emailtype) {
				if(array_key_exists($emailtype, $mail_config) && is_array($mail_config[$emailtype])) {
					// check explicitly if mail and name fields are false and if the formfield fields exist
					if(($mail_config[$emailtype]['email']===false && $mail_config[$emailtype]['name']===false)
					&&($mail_config[$emailtype]['formfield_email'] && $mail_config[$emailtype]['formfield_name'])) {
						foreach($this->form->fields as $value) {
							if($value['name']==$mail_config[$emailtype]['formfield_email'] && isset($value['post_value'])) {
								$_mail[$emailtype]['email'] = $value['post_value'];
							}
							if($value['name']==$mail_config[$emailtype]['formfield_name'] && isset($value['post_value'])) {
								$_mail[$emailtype]['name'] = $value['post_value'];
							}
						}
						if($_mail[$emailtype]['email'] && $_mail[$emailtype]['name']) {
							debug('set email '.$emailtype.' from form values '. $_mail[$emailtype]['email'] .' - '. $_mail[$emailtype]['name']);
						}
					} else {
						$_mail[$emailtype]['email'] = $mail_config[$emailtype]['email'];
						$_mail[$emailtype]['name'] = $mail_config[$emailtype]['name'];
						debug('set email '.$emailtype.' from form config '. $_mail[$emailtype]['email'] .' - '. $_mail[$emailtype]['namee']);
					}
				}
			}

			// we have subject sender and recipient, lets start
			if($subject && $_mail['sender']['email'] && $_mail['recipient']['email']) {
				$this->formsender = array('email' => $_mail['sender']['email'], 'name' => $_mail['sender']['name']);
				$message = Swift_Message::newInstance($subject)
					->setFrom(array($_mail['sender']['email'] => $_mail['sender']['name']))
					->setTo(array($_mail['recipient']['email'] => $_mail['recipient']['name']));
			} else {
				debug('trying to send email without the necessary data - please provide at least a subject, sender and recipient, and dont forget the message too');
			}

			// carbon copy adresses
			if(array_key_exists('cc', $_mail) && is_array($_mail['cc'])) {
			    $_mail['cc']['name'] = (isset($_mail['cc']['name']))?$_mail['cc']['name']:$_mail['recipient']['name'];
				$message->setCc(array($_mail['cc']['email'] => $_mail['cc']['name']));
			}

			// bcc is also possible for stealthy purposes
			if(array_key_exists('bcc', $_mail) && is_array($_mail['bcc'])) {
			    $_mail['bcc']['name'] = (isset($_mail['bcc']['name']))?$_mail['bcc']['name']:$_mail['recipient']['name'];
				$message->setBcc(array($_mail['bcc']['email'] => $_mail['bcc']['name']));
			}


			// mail sent and form complete
			$mail_template = $this->find_templates($this->config['templates']['mailreply']);
			$display_template = $this->find_templates($this->config['templates']['confirmation']);

			// prepare all key/value pairs for %posted_data%
			$key_values = '';
			foreach($this->form->fields as $value) {
				// exclude hidden system fields
				if($value['post_value'] && !in_array($value['name'], array('check_referrer', 'hidden_formid'))) {
					$key_values .= $value['name'] .": %". $value['name'] ."%\n";
				}
			}
			$debug_template = $key_values;

			// replace %posted_data% with all key/value pairs
			$mail_template = str_replace('%posted_data%', $key_values, $mail_template);
			$display_template = str_replace('%posted_data%', nl2br($key_values), $display_template);

			 // for empty templates - add all key/value pairs
			if(empty($mail_template)) {
				$mail_template = $key_values;
			}
			if(empty($display_template)) {
				$display_template = nl2br($key_values);
			}

			// replace value templates
			foreach($this->form->fields as $value) {
				if($value['type']=='checkbox' && $value['post_value']) {
					$mail_template = str_replace('%'.$value['name'].'%', $value['value'], $mail_template);
					$display_template = str_replace('%'.$value['name'].'%', $value['value'], $display_template);
				} elseif($value['type']!='custom' && $value['post_value']) {
					$mail_template = str_replace('%'.$value['name'].'%', $value['post_value'], $mail_template);
					$display_template = str_replace('%'.$value['name'].'%', $value['post_value'], $display_template);
				} elseif(in_array($value['type'], array('file', 'upload', 'uploadselectbox'))) {
					// debug_printr(array('this file'=> $_FILES[$value['name']], 'value'=> $value, 'get' => $_GET, 'post' => $_POST, 'files' => $_FILES));
					$filename = $_POST[$value['name']];

					$path = $value['upload_dir'];
					if(file_exists($path.$filename)) {
						//debug('file exists, attempting to attach');
						$mail_template = str_replace('%'.$value['name'].'%', $filename .' '. ft('(attached file)'), $mail_template);
						$display_template = str_replace('%'.$value['name'].'%', $filename, $display_template);
						
						$attachment = Swift_Attachment::fromPath($path.$filename);  					
						//Attach it to the message
						$message->attach($attachment);
					} else {
						//debug('file attachment is not found');
					}
					

				} else {
					$mail_template = str_replace('%'.$value['name'].'%', '', $mail_template);
					$display_template = str_replace('%'.$value['name'].'%', '', $display_template);
				}
				$debug_template = str_replace('%'.$value['name'].'%', $value['post_value'], $debug_template);
			}
			
			debug("Submitted keys : values that can be used in form mail templates:\n\n". $debug_template);
			
			// The unique ID of a submission
			$unique_id = substr(md5(session_id() ."-". mt_rand()),-10);
			$mail_template = str_replace('%uniqid%', $unique_id, $mail_template);
			$display_template = str_replace('%uniqid%', $unique_id, $display_template);

			$message->setBody($mail_template);
			//Send the message
			$this->send_email_success = $mailer->send($message);
			debug('send_email_success = '.$this->send_email_success);

		}

		if($this->send_email_success == true) {
			$textmessage = $display_template;
		} else {
			$textmessage = '<p class="warning">'.ft('Please note: Your message has not been sent. There is an error in the script configuration.').'</p>';
		}
		// debug($textmessage);
		$this->mailconfirmation = $textmessage;
		// debug('exiting send_message');
	}

	function execute_form() {
		// get the unique form identifier
		if(is_array($_REQUEST) && !empty($_REQUEST['hidden_formid'])) {
			$this->formsessionkey = getDefault($_POST['hidden_formid'], $_GET['hidden_formid']);
			debug('got '. $this->formsessionkey .' from submitted data');
		}
		// redirect the possible get/post and other vars if we're in the wrong form
		if(
		   is_array($_REQUEST)
		   && isset($_REQUEST)
		   && $_REQUEST['hidden_formid'] != $this->formsessionkey) {
			debug('form identifier is not right: '. $_REQUEST['hidden_formid'] ." != ". $this->formsessionkey);
			//debug('redirected request, post and get');
			$oldrequest = $_REQUEST;
			$oldpost = $_POST;
			$oldget = $_GET;
			unset($_REQUEST);
			unset($_POST);
			unset($_GET);
		}
		// set all request values to the real stuff
		if(is_array($_REQUEST) && !empty($_REQUEST)) {
			foreach($_REQUEST as $key => $value) {
				if(isset($this->config['fields'][$key]['listentoget']) && $this->config['fields'][$key]['listentoget']==true) {
					$this->config['fields'][$key]['value'] = $this->safe_string($value);
				}
			}
		}
		// load the unique formsessionkey
		// or request a new one
		if(!isset($this->formsessionkey)) {
			if(function_exists('formbuilderlogRequestToken') && ($this->config['enable_logging']==true)) {
				$this->formsessionkey = formbuilderlogRequestToken($this->config['id'], $this->form->spamkey);
			} else {
				$this->formsessionkey = md5($_SERVER['REMOTE_ADDR'].$_SERVER["HTTP_USER_AGENT"].$this->form->spamkey);
			}
		}
		
		

		if(isset($this->config['pre_html'])) {
			$this->form->add(array('type' => 'custom', 'text' => $this->config['pre_html']));
		}
		// prepare all fieldsets
		foreach($this->config['fieldsets'] as $fieldset => $fieldset_values) {
			if($fieldset && !$fieldset_values['id']) {
				$safeid = preg_replace('/[^a-z0-9\-]/i','',trim(strtolower(strip_tags($fieldset))));
			} elseif(!$fieldset_values['id']) {
				$safeid = 'fieldset-'. preg_replace('/[^a-z0-9]/i','',trim(strtolower(strip_tags($fieldset_values['label']))));
			} else {
				$safeid = preg_replace('/[^a-z0-9]/i','',trim(strtolower(strip_tags($fieldset_values['id']))));
			}
			if($this->config['fieldsets']['class']) {
				$this->config['fieldsets']['class'] = preg_replace('/[^a-z0-9]/i','',trim(strtolower(strip_tags($this->config['fieldsets']['class']))));
				$this->form->add(array('type' => 'custom', 'text' => str_replace('%class%', $this->config['fieldsets']['class'], '<fieldset id="'.$safeid.'" class="formfieldset %class%">')));
			} else {
				$this->form->add(array('type' => 'custom', 'text' => '<fieldset id="'.$safeid.'" class="formfieldset">'));
			}
			if(!empty($fieldset_values['label'])) {
				$this->form->add(array('type' => 'custom', 'text' => '<legend>'.$fieldset_values['label'].'</legend>'));
			}
			if(isset($fieldset_values['pre_html'])) {
				$this->form->add(array('type' => 'custom', 'text' => $fieldset_values['pre_html']));
			}
			foreach($fieldset_values['fields'] as $field) {
				if(is_array($this->config['fields'][$field])) {
					if($this->config['fields'][$field]['class']) {
						
						$this->config['fields'][$field]['class'] = preg_replace('/[^a-z0-9]/i','',trim(strtolower(strip_tags($this->config['fields'][$field]['class']))));
						$this->form->add(array('type' => 'custom', 'text' => str_replace('%class%', $this->config['fields'][$field]['class'],'<div class="formrow fieldsetformrow %class%">')));	
					} else {
						$this->form->add(array('type' => 'custom', 'text' => '<div class="formrow fieldsetformrow">'));
					}
					if(isset($this->config['fields'][$field]['pre_html'])) {
						$this->form->add(array('type' => 'custom', 'text' => $this->config['fields'][$field]['pre_html']));
					}
					$this->form->add($this->config['fields'][$field]);

					if(isset($this->config['fields'][$field]['post_html'])) {
						$this->form->add(array('type' => 'custom', 'text' => $this->config['fields'][$field]['post_html']));
					}
					$this->form->add(array('type' => 'custom', 'text' => '</div>'));
					// remove from form
				} else {
					print '<p class="error">'.sprintf(ft('Error in field definition for: %s'), '<em>'. $field .'</em>')."</p>\n";
				}
				$this->config['fields'][$field]['isadded']=true;
			}
			if(isset($fieldset_values['post_html'])) {
				$this->form->add(array('type' => 'custom', 'text' => $fieldset_values['post_html']));
			}
			$this->form->add(array('type' => 'custom', 'text' => '</fieldset>'));
		}

		// add a form identifier to every form
		$this->config['fields']['formsk'] = array(
			'name' => 'hidden_formid',
			'label' => '',
			'type' => 'hidden',
			'value' => $this->formsessionkey,
			'requiredmessage' => '',
			'validation' => 'string',
			'error' => ''
		);

		//debug($_SERVER['REMOTE_ADDR']. ' ' .$this->form->spamkey . ' ' . $this->formsubmissionkey );
		$this->formsubmissionkey = md5($_SERVER['REMOTE_ADDR'].$_SERVER["HTTP_USER_AGENT"].$this->form->spamkey);
		$this->config['fields']['referrer'] = array(
			'name' => 'check_referrer',
			'label' => '',
			'type' => 'hidden',
			'value' => '',
			'requiredmessage' => '',
			'validation' => 'string',
			'error' => '',
		);
		$this->config['fields']['refererscript'] = array(
			'type' => 'custom',
			'text' => '<script type="text/javascript">jQuery(function($){$("#'.$this->config['id'].' input[name=check_referrer]").val("'.$this->formsubmissionkey.'");});</script>'. "\n" .'<noscript>'.ft('This form will not work if you have not enabled javascript in your browser.').'</noscript>'
		);



		// prepare all other fields if there are any left
		if(!empty($this->config['fields'])) {
			foreach($this->config['fields'] as $field => $field_value) {
				if(!array_key_exists('isadded', $this->config['fields'][$field]) || $this->config['fields'][$field]['isadded']!=true) {
					if(is_array($field_value) && $field_value['type']=='markup') {
						// works only in quicktags
						$this->form->add(array('type' => 'custom', 'text' => '<div class="formrow extraformrow" id="'.$field_value['name'].'">'.$field_value['label'].'</div>'));
						$this->config['fields'][$field]['isadded']=true;
					} elseif(is_array($field_value) && $field_value['type']!='hidden') {
						if($field_value['class']) {
							$field_value['class'] = preg_replace('/[^a-z0-9]/i','',trim(strtolower(strip_tags($field_value['class']))));
							$this->form->add(array('type' => 'custom', 'text' => str_replace('%class%', $field_value['class'], '<div class="formrow extraformrow %class%">')));	
						} else {
							$this->form->add(array('type' => 'custom', 'text' => '<div class="formrow extraformrow">'));
						}
						if(isset($field_value['pre_html'])) {
							$this->form->add(array('type' => 'custom', 'text' => $field_value['pre_html']));
						}
						$this->form->add($field_value);
						if(isset($field_value['post_html'])) {
							$this->form->add(array('type' => 'custom', 'text' => $field_value['post_html']));
						}
						$this->form->add(array('type' => 'custom', 'text' => '</div>'));
						// remove from form
						$this->config['fields'][$field]['isadded']=true;
					} elseif(is_array($field_value) && $field_value['type']=='hidden') {
						$this->form->add($field_value);
						$this->config['fields'][$field]['isadded']=true;
					} else {
						print '<p class="error">'.sprintf(ft('Error in field definition for: %s'), '<em>'. $field .'</em>')."</p>\n";
					}
				}
			}
		}


		if(is_array($this->config['buttons'])) {
			foreach($this->config['buttons'] as $button => $buttonvalue) {
				if($buttonvalue['type'] == 'submit') {
					$this->form->submit = $buttonvalue['value'];
				} else {
					$this->form->add($buttonvalue);
				}
			}
		}

		if(isset($this->config['post_html'])) {
			$this->form->add(array('type' => 'custom', 'text' => $this->config['post_html']));
		}
		
		if(isset($_POST['check_referrer']) && ($_POST['check_referrer'] != $this->formsubmissionkey)) {
			debug('spam referrer is not right: '. $_POST['check_referrer'] .' should be '. $this->formsubmissionkey." - javascript must be disabled or someone is a spammer.");
			$this->form->add(array('type' => 'custom', 'text' => '<noscript><p>'. ft('To prevent spam JavaScript must be enabled for submitting this form.') .'</p></noscript>'));
			$this->validationvalue = 0;
		} elseif(isset($_POST['hidden_formid']) && ($_POST['hidden_formid'] != $this->formsessionkey)) {
			debug('form hidden_formid identifier is not right: '. $_POST['hidden_formid'] ." != ".$this->formsessionkey  ."\nYou were probably submitting another form.");
			$this->validationvalue = 0;
		} elseif(is_array($_REQUEST) && !array_key_exists('silent', $_REQUEST) || $_REQUEST['silent']!=true) {
			//debug('form identifier is right or empty: '. $_POST['hidden_formid'] ." != ".$this->formsessionkey);
			$this->validationvalue = $this->form->validate();
			//debug('form validated... status is: '.$this->validationvalue);
		} else {
			//debug('silent or validation skipped');
			$this->validationvalue = 0;
		}
		//debug('Validation = '.$this->validationvalue);
		if($this->validationvalue>=2) {
			$this->send_message();

			if($this->config['enable_logging']==true) {
				$formbuilderlog = new FormbuilderLogSql();

				foreach($this->form->fields as $value) {
					// exclude hidden system fields
					if(!empty($value['name'])) {
						$form_fields[] = $value['name'];
						$form_values[] = ($value['post_value'])?$value['post_value']:'null';
					}
				}
				
				$senderemail = ($this->formsender['email'])?$this->formsender['email']:'example@example.com';
				$sendername = ($this->formsender['name'])?$this->formsender['name']:'Example User';

				$formbuildersubmission = $formbuilderlog->getFormbuilderLogSID($_POST['hidden_formid']);

				if(!isset($formbuildersubmission['submission_id'])) {
					debug('something is wrong with submission id '. $formbuildersubmission['submission_id'] .' it looks like it was removed by the scheduler before the form was completed.');
					
					// insert submission
					// this will hardly happen, because there should usually be a token in the db
					$logsubmission = array(
							'form_id' =>$this->config['id'],
							'submission_id' => $_POST['hidden_formid'],
							'last_updated' => null,
							'user_email' => $senderemail,
							'user_name' => $sendername,
							'user_ip' => $_SERVER['REMOTE_ADDR'],
							'user_hostname' => $_SERVER['REMOTE_HOST'],
							'user_browser' => $_SERVER['HTTP_USER_AGENT'],
							'form_fields' => serialize($form_fields),
							'form_values' => serialize($form_values),
							'status' => 'new'
						);
				} else {
					// update an existing submission
					$logsubmission = $formbuildersubmission;

					if($this->config['id']!=$logsubmission['form_id']) {
						$logsubmission['form_id'] = $this->config['id'];
					}
					if($_SERVER['REMOTE_ADDR']!=$logsubmission['user_ip']) {
						$logsubmission['user_ip'] = $_SERVER['REMOTE_ADDR'];
					}
					if($_SERVER['REMOTE_HOST']!=$logsubmission['user_hostname']) {
						$logsubmission['user_hostname'] = $_SERVER['REMOTE_HOST'];
					}
					if($_SERVER['HTTP_USER_AGENT']!=$logsubmission['user_browser'] ) {
						$logsubmission['user_browser'] = $_SERVER['HTTP_USER_AGENT'];
					}
					
					// these values will always change
					$logsubmission['last_updated'] =  null;
					$logsubmission['user_email'] = $senderemail;
					$logsubmission['user_name'] = $sendername;
					if(!empty($form_fields)) {
						$logsubmission['form_fields'] = serialize($form_fields);
					}
					if(!empty($form_values)) {
						$logsubmission['form_values'] = serialize($form_values);
					}
					//debug_printr($logsubmission);
					
				}
				$formbuilderlog->saveFormbuilderLog($logsubmission);
				// cleanup junk vars
				unset($formbuildersubmission);
				unset($logsubmission);
			}

			// kill the hidden_formid for the next time this function will be called
			$_POST['hidden_formid'] = 'already sent';

			//debug('Message = '.$this->mailconfirmation);
			//debug('redirect = '.$this->config['redirect']);
			if(!isset($this->config['redirect']) || !$this->config['redirect']) {
				// place the response a global in case the form is reset by multiple submissions
				$_POST['hidden_confirmation'] = $this->mailconfirmation;
				print $this->mailconfirmation;
			} else {
				//print $this->mailconfirmation;
				//print '<p>PivotX needs a redirect funtion to send you here: <a href="'.$this->config['redirect'].'">'.$this->config['redirect'].'</a></p>';
				if(!substr_compare($this->config['redirect'], 'http://', 0, 7)) {
					$this->config['redirect'] = $PIVOTX['paths']['pivotx_url'] . $this->config['redirect'];
				}
				header("Location: ".$this->config['redirect']);
				exit;
			}
		} elseif($_POST['hidden_formid']=='already sent') {
			// in case the form was processed multiple times
			print $_POST['hidden_confirmation'];
		} else {
			$this->form->display();
		}
		// return to the normal get/post and other vars if we're in the wrong form
		if(is_array($oldrequest) && is_array($oldpost) && is_array($oldget)) {
			//debug('reloaded request, post and get');
			
			$_REQUEST = $oldrequest;
			$_POST = $oldpost;
			$_GET = $oldget;
			unset($oldrequest);
			unset($oldpost);
			unset($oldget);
		}
	}
}
?>