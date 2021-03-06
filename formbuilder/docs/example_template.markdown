# Example template

This template is based on a normal entry template from the skinny theme.
To use this, copy the code from 'template start' into a template file in your theme and enable `allow_php_in_templates` in your PivotX configuration.

    <!-- template start -->
    [[ include file="bare_bones/_sub_header.html" ]]
    
    <div id="content">
        <div id="content-inner">
    
            <h2>
                <a href="[[ link hrefonly=1 ]]">[[title]]</a>
            </h2>
    
            <h3>[[subtitle]]</h3>
    
            <p class="date">
                [[ date format="%dayname% %day% %monthname% %year% at %hour12%&#58;%minute% %ampm%." ]]
                [[ tags ]]
                [[ editlink format="Edit" prefix=" - " ]]
            </p>
    
            [[ introduction ]]
    
    <div id="mailformwrapper">
    [[ php ]]
    
    $mail_config = array(
      'subject' => 'Contact form message',
      'recipient' => array(
        'email' => 'contactform@example.com',
        'name' => 'Contact form'
      ),
      'sender' => array(
        'email' => 'contactform@example.com',
        'name' => 'Contact form'
      ),
      'cc' => array(
        'email' => 'contactform@example.com',
        'name' => 'Contact form'
      ),
      'method' => 'mail', // mail | smtp
      'smtp' => array( // only used if smtp method is selected
        'login' => 'contactform@example.com',
        'password' => 'password',
        'server' => 'mail.example.com'
      )
    );
    
    $fields = array(
      'name' => array(
        'name' => 'name',
        'label' => 'Name',
        'type' => 'text',
        'isrequired' => true,
        'requiredmessage' => '&quot;Name&quot; is a required field. Please enter a name',
        'validation' => 'string',
        'error' => 'Please enter a name'
      ),
      'company' => array(
        'name' => 'company',
        'label' => 'Company',
        'type' => 'text',
        'isrequired' => false,
        'requiredmessage' => '',
        'validation' => 'ifany|string',
        'error' => 'Please enter a valid company name'
      ),
      'email' => array(
        'name' => 'email',
        'label' => 'E-mail address',
        'type' => 'text',
        'isrequired' => true,
        'requiredmessage' => '&quot;E-mail address&quot; is a required field. Please enter a valid e-mail address',
        'validation' => 'email',
        'error' => 'Please enter a correct e-mail address',
        'pre_html' => '<p>Your e-mail address is safe with us. We will never sell or give away your e-mail address.</p>',
      ),
      'phone' => array(
        'name' => 'phone',
        'label' => 'Phone number',
        'type' => 'text',
        'isrequired' => true,
        'requiredmessage' => '&quot;Phone Number&quot; is a required field. Please enter a phone number',
        'validation' => 'phonenumber',
        'error' => 'Please enter a correct phone number'
      ),
      'message' => array(
        'name' => 'message',
        'label' => 'Message',
        'type' => 'textarea',
        'isrequired' => true,
        'requiredmessage' => '&quot;Message&quot; is a required field. Please enter a message',
        'validation' => 'string',
        'error' => 'Please enter a message'
      ),
      'referrer' => array(
        'name' => 'referrer',
        'label' => 'How have you found us?',
        'type' => 'select',
        'isrequired' => true,
        'requiredmessage' => 'Please select an option',
        'validation' => 'options',
        'error' => 'Please select an option.',
        'default' => array('invalid_choice' => 'Please select an option'),
        'options' => array(
          'invalid_choice' => 'Please select an option', // special value, not selectable
          'magazine advert' => 'Advert in a magazine',
          'website advert' => 'Advert on a website',
          'search result' => 'Search result on google or another search engine',
          'other' => 'Other'
        )
      ),
      'newsletter' => array(
        'name' => 'newsletter',
        'label' => 'Do you want to subscribe to our newsletter?',
        'type' => 'checkbox',
        'isrequired' => false,
        'requiredmessage' => '',
        'validation' => '',
        'default' => 'yes',
        'value' => 'yes',
        'error' => 'Please check the input for newsletter',
        'post_html' => '<p>If you subscribe to our newsletter, we will send you a monthly message with funny examples.</p>',
      )
    );
    
    
    
    $config = array(
      'id' => 'contactform',
      'name' => 'contactform',
      'action' => $_SERVER["REQUEST_URI"],
      'templates' => array(
        'confirmation' => 'form.confirm.tpl.php', // filename in formpath or html string
        'elements' => 'formclass_defaulthtml.php',
        'mailreply' => 'form.mail.tpl.php' // filename in formpath or html string
      ),
      'method' => 'post', // get | post
      'encoding' => '', // multipart/form-data | empty
      'buttons' => array(
        'verzenden' => array(
          'type' => 'submit',
          'label' => '',
          'value' => 'Send message'
        )
      ),
      'fieldsets' => array(
        'personal' => array(
          'id' => 'personal',
          'label' => 'Personal',
          'fields' => array('name', 'company', 'email', 'phone')
        ),
        'messagebox' => array(
          'id' => 'questions',
          'label' => 'Your message or question',
          'fields' => array('message'),
          'post_html' => '<p>We like to hear from you, but please keep it on topic.</p>',
        ),
        'extra' => array(
          'label' => '',
          'fields' => array('referrer', 'newsletter')
        )
      ),
      'fields' => $fields,
      'mail_config' => $mail_config,
      'pre_html' => '<p>A short text before the form.</p>',
      'post_html' => '<p>A short text after the form.</p><p class="message">Fields marked with <span class="required">*</span> are required and must be entered.</p>'
    );
    
    $config['action'] = '';
    
    $formclass = getcwd().'/extensions/formbuilder/form.class.php';
    if(file_exists($formclass) && is_readable($formclass)) {
      include_once($formclass);
    } else {
      echo 'Er is iets mis gegaan met het formulier';
    }
    
    $form = new FormBuilder($config);
    $form->execute_form();
    [[ /php ]]
    </div>
    
            [[ body ]]
    
        </div>
    </div>
    
    [[ include file="bare_bones/_sub_sidebar.html" ]]
    
    [[ include file="bare_bones/_sub_footer.html" ]]
