<?php

/**
 * Installer
 *
 * @package		installer
 *
 * @author		Tijs Verkoyen <tijs@netlash.com>
 * @author		Davy Hellemans <davy@netlash.com>
 * @since		2.0
 */
class Installer
{
	/**
	 * Form instance
	 *
	 * @var	SpoonForm
	 */
	private $frm;


	/**
	 * Template instance
	 *
	 * @var	SpoonTemplate
	 */
	private $tpl;


	/**
	 * Default constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		// do init
		$this->init();

		// get step
		$step = SpoonFilter::getGetValue('step', array(1, 2, 3, 4), 1, 'int');

		// go to step 1 if your requirements were not ok
		if($step != 1 && !$this->checkRequirements()) $step = 1;

		// in step 1 we don't know where Spoon is located so we cant use the template-engine
		if($step != 1)
		{
			// create the template
			$this->tpl = new SpoonTemplate();

			// set some options
			$this->tpl->setCompileDirectory(PATH_WWW .'/install/cache');
			$this->tpl->setForceCompile(SPOON_DEBUG);
		}

		// execute the correct step
		switch($step)
		{
			case 1:
				$this->doStep1();
			break;

			case 2:
				$this->doStep2();
			break;

			case 3:
				$this->doStep3();
			break;

			case 4:
				$this->doStep4();
			break;
		}

		// parse the form
		if($this->frm !== null && $this->tpl !== null) $this->frm->parse($this->tpl);

		// show the template
		if($this->tpl !== null) $this->tpl->display(PATH_WWW .'/install/layout/templates/'. $step .'.tpl');
	}


	/**
	 * Build the language files
	 *
	 * @return	void
	 * @param	string $language		The language to build the locale-file for.
	 * @param	string $application		The application to build the locale-file for.
	 */
	public function buildCache(SpoonDatabase $db, $language, $application)
	{
		// get types
		$types = $db->getEnumValues('locale', 'type');

		// get locale for backend
		$locale = (array) $db->getRecords('SELECT type, module, name, value
											FROM locale
											WHERE language = ? AND application = ?
											ORDER BY type ASC, name ASC, module ASC;',
											array((string) $language, (string) $application));

		// start generating PHP
		$value = '<?php' ."\n";
		$value .= '/**' ."\n";
		$value .= ' *' ."\n";
		$value .= ' * This file is generated by the Backend, it contains' ."\n";
		$value .= ' * more information about the locale. Do NOT edit.' ."\n";
		$value .= ' * ' ."\n";
		$value .= ' * @author		Backend' ."\n";
		$value .= ' * @generated	'. date('Y-m-d H:i:s') ."\n";
		$value .= ' */' ."\n";
		$value .= "\n";

		// loop types
		foreach($types as $type)
		{
			// default module
			$modules = array('core');

			// continue output
			$value .= "\n";
			$value .= '// init var'. "\n";
			$value .= '$'. $type .' = array();' ."\n";
			$value .= '$'. $type .'[\'core\'] = array();' ."\n";

			// loop locale
			foreach($locale as $i => $item)
			{
				// types match
				if($item['type'] == $type)
				{
					// new module
					if(!in_array($item['module'], $modules))
					{
						$value .= '$'. $type .'[\''. $item['module'] .'\'] = array();'. "\n";
						$modules[] = $item['module'];
					}

					// parse
					if($application == 'backend') $value .= '$'. $type .'[\''. $item['module'] .'\'][\''. $item['name'] .'\'] = \''. str_replace('\"', '"', addslashes($item['value'])) .'\';'. "\n";
					else $value .= '$'. $type .'[\''. $item['name'] .'\'] = \''. str_replace('\"', '"', addslashes($item['value'])) .'\';'. "\n";

					// unset
					unset($locale[$i]);
				}
			}
		}

		// close php
		$value .= "\n";
		$value .= '?>';

		// store
		SpoonFile::setContent(PATH_WWW .'/'. $application .'/cache/locale/'. $language .'.php', $value);
	}


	private function checkRequirements()
	{
		// check the PHP version. At this moment we require at least 5.2
		$version = (int) str_replace('.', '', PHP_VERSION);
		if($version < 520) return false;

		// check if cURL is loaded
		if(!extension_loaded('curl')) return false;

		// check if SimpleXML is loaded
		if(!extension_loaded('SimpleXML')) return false;

		// check if SPL is loaded
		if(!extension_loaded('SPL')) return false;

		// check if PDO is loaded
		if(!extension_loaded('PDO')) return false;

		// check if mbstring is loaded
		if(!extension_loaded('mbstring')) return false;

		// check if iconv is loaded
		if(!extension_loaded('iconv')) return false;

		// check if GD is loaded and the correct version is installed
		if(!extension_loaded('gd') && function_exists('gd_info')) return false;

		// check if the backend-cache-directory is writable
		if(!is_writable(PATH_WWW .'/backend/cache/')) return false;

		// check if the frontend-cache-directory is writable
		if(!is_writable(PATH_WWW .'/frontend/cache/')) return false;

		// check if the frontend-files-directory is writable
		if(!is_writable(PATH_WWW .'/frontend/files/')) return false;

		// check if the library-directory is writable
		if(!is_writable(PATH_LIBRARY)) return false;

		// check if the installer-directory is writable
		if(!is_writable(PATH_WWW .'/install')) return false;

		// does the globals.example.php file exist
		if(!file_exists(PATH_LIBRARY .'/globals.example.php') || !is_readable(PATH_LIBRARY .'/globals.example.php')) return false;

		// does the globals_backend.example.php file exist
		if(!file_exists(PATH_LIBRARY .'/globals_backend.example.php') || !is_readable(PATH_LIBRARY .'/globals_backend.example.php')) return false;

		// does the globals_frontend.example.php file exist
		if(!file_exists(PATH_LIBRARY .'/globals_frontend.example.php') || !is_readable(PATH_LIBRARY .'/globals_frontend.example.php')) return false;

		// every check was passed
		return true;
	}


	/**
	 * Execute step 1
	 *
	 * @return	void
	 */
	private function doStep1()
	{
		// init vars
		$hasError = false;
		$variables = array();

		// init
		$variables['error'] = '';
		$variables['PATH_WWW'] = PATH_WWW;
		$variables['PATH_LIBRARY'] = PATH_LIBRARY;

		// check the PHP version. At this moment we require at least 5.2
		$version = (int) str_replace('.', '', PHP_VERSION);
		if($version >= 520)
		{
			$variables['phpVersion'] = 'ok';
			$variables['phpVersionStatus'] = 'ok';
		}
		else
		{
			$variables['phpVersion'] = 'nok';
			$variables['phpVersionStatus'] = 'not ok';
			$hasError = true;
		}

		// check if cURL is loaded
		if(extension_loaded('curl'))
		{
			$variables['extensionCURL'] = 'ok';
			$variables['extensionCURLStatus'] = 'ok';
		}
		else
		{
			$variables['extensionCURL'] = 'nok';
			$variables['extensionCURLStatus'] = 'not ok';
			$hasError = true;
		}

		// check if SimpleXML is loaded
		if(extension_loaded('SimpleXML'))
		{
			$variables['extensionSimpleXML'] = 'ok';
			$variables['extensionSimpleXMLStatus'] = 'ok';
		}
		else
		{
			$variables['extensionSimpleXML'] = 'nok';
			$variables['extensionSimpleXMLStatus'] = 'not ok';
			$hasError = true;
		}

		// check if SPL is loaded
		if(extension_loaded('SPL'))
		{
			$variables['extensionSPL'] = 'ok';
			$variables['extensionSPLStatus'] = 'ok';
		}
		else
		{
			$variables['extensionSPL'] = 'nok';
			$variables['extensionSPLStatus'] = 'not ok';
			$hasError = true;
		}

		// check if PDO is loaded
		if(extension_loaded('PDO'))
		{
			$variables['extensionPDO'] = 'ok';
			$variables['extensionPDOStatus'] = 'ok';
		}
		else
		{
			$variables['extensionPDO'] = 'nok';
			$variables['extensionPDOStatus'] = 'not ok';
			$hasError = true;
		}

		// check if mbstring is loaded
		if(extension_loaded('mbstring'))
		{
			$variables['extensionMBString'] = 'ok';
			$variables['extensionMBStringStatus'] = 'ok';
		}
		else
		{
			$variables['extensionMBString'] = 'nok';
			$variables['extensionMBStringStatus'] = 'not ok';
			$hasError = true;
		}

		// check if iconv is loaded
		if(extension_loaded('iconv'))
		{
			$variables['extensionIconv'] = 'ok';
			$variables['extensionIconvStatus'] = 'ok';
		}
		else
		{
			$variables['extensionIconv'] = 'nok';
			$variables['extensionIconvStatus'] = 'not ok';
			$hasError = true;
		}

		// check if GD is loaded and the correct version is installed
		if(extension_loaded('gd') && function_exists('gd_info'))
		{
			$variables['extensionGD2'] = 'ok';
			$variables['extensionGD2Status'] = 'ok';
		}
		else
		{
			$variables['extensionGD2'] = 'nok';
			$variables['extensionGD2Status'] = 'not ok';
			$hasError = true;
		}

		// check if the backend-cache-directory is writable
		if(is_writable(PATH_WWW .'/backend/cache/'))
		{
			$variables['fileSystemBackendCache'] = 'ok';
			$variables['fileSystemBackendCacheStatus'] = 'ok';
		}
		else
		{
			$variables['fileSystemBackendCache'] = 'nok';
			$variables['fileSystemBackendCacheStatus'] = 'not ok';
			$hasError = true;
		}

		// check if the frontend-cache-directory is writable
		if(is_writable(PATH_WWW .'/frontend/cache/'))
		{
			$variables['fileSystemFrontendCache'] = 'ok';
			$variables['fileSystemFrontendCacheStatus'] = 'ok';
		}
		else
		{
			$variables['fileSystemFrontendCache'] = 'nok';
			$variables['fileSystemFrontendCacheStatus'] = 'not ok';
			$hasError = true;
		}

		// check if the frontend-files-directory is writable
		if(is_writable(PATH_WWW .'/frontend/files/'))
		{
			$variables['fileSystemFrontendFiles'] = 'ok';
			$variables['fileSystemFrontendFilesStatus'] = 'ok';
		}
		else
		{
			$variables['fileSystemFrontendFiles'] = 'nok';
			$variables['fileSystemFrontendFilesStatus'] = 'not ok';
			$hasError = true;
		}

		// check if the library-directory is writable
		if(is_writable(PATH_LIBRARY))
		{
			$variables['fileSystemLibrary'] = 'ok';
			$variables['fileSystemLibraryStatus'] = 'ok';
		}
		else
		{
			$variables['fileSystemLibrary'] = 'nok';
			$variables['fileSystemLibraryStatus'] = 'not ok';
			$hasError = true;
		}

		// check if the installer-directory is writable
		if(is_writable(PATH_WWW .'/install'))
		{
			$variables['fileSystemInstaller'] = 'ok';
			$variables['fileSystemInstallerStatus'] = 'ok';
		}
		else
		{
			$variables['fileSystemInstaller'] = 'nok';
			$variables['fileSystemInstallerStatus'] = 'not ok';
			$hasError = true;
		}

		// does the globals.example.php file exist
		if(file_exists(PATH_LIBRARY .'/globals.example.php') && is_readable(PATH_LIBRARY .'/globals.example.php'))
		{
			$variables['fileSystemGlobals'] = 'ok';
			$variables['fileSystemGlobalsStatus'] = 'ok';
		}
		else
		{
			$variables['fileSystemGlobals'] = 'nok';
			$variables['fileSystemGlobalsStatus'] = 'not ok';
			$hasError = true;
		}

		// does the globals_backend.example.php file exist
		if(file_exists(PATH_LIBRARY .'/globals_backend.example.php') && is_readable(PATH_LIBRARY .'/globals_backend.example.php'))
		{
			$variables['fileSystemGlobalsBackend'] = 'ok';
			$variables['fileSystemGlobalsBackendStatus'] = 'ok';
		}
		else
		{
			$variables['fileSystemGlobalsBackend'] = 'nok';
			$variables['fileSystemGlobalsBackendStatus'] = 'not ok';
			$hasError = true;
		}

		// does the globals_frontend.example.php file exist
		if(file_exists(PATH_LIBRARY .'/globals_frontend.example.php') && is_readable(PATH_LIBRARY .'/globals_frontend.example.php'))
		{
			$variables['fileSystemGlobalsFrontend'] = 'ok';
			$variables['fileSystemGlobalsFrontendStatus'] = 'ok';
		}
		else
		{
			$variables['fileSystemGlobalsFrontend'] = 'nok';
			$variables['fileSystemGlobalsFrontendStatus'] = 'not ok';
			$hasError = true;
		}

		// has errors
		if($hasError)
		{
			// assign the variable
			$variables['error'] = '<div class="message errorMessage singleMessage"><p>Fix the items below that are marked as <em>Not ok</em>.</p></div><br />';
			$variables['nextButton'] = '&nbsp;';
		}

		// no errors detected
		else
		{
			// get values
			$buttonValue = SpoonFilter::getPostValue('installer', array('Next'), 'N');
			$stepValue = SpoonFilter::getPostValue('step', array(1, 2), 'N');

			// is the form submitted?
			if($buttonValue != 'N' && $stepValue != 'N') SpoonHTTP::redirect('index.php?step=2');

			// button
			$variables['nextButton'] = '<input id="installerButton" class="inputButton button mainButton" type="submit" name="installer" value="Next" />';
		}

		// build 'template'
		$tpl = SpoonFile::getContent(PATH_WWW .'/install/layout/templates/1.tpl');

		// build the search & replace array
		$search = array_keys($variables);
		$replace = array_values($variables);

		// loop search values
		foreach($search as $key => $value) $search[$key] = '{$'. $value .'}';

		// build output
		$output = str_replace($search, $replace, $tpl);

		// show
		echo $output;

		// stop the script
		exit;
	}


	/**
	 * Execute step 2
	 *
	 * @return	void
	 */
	private function doStep2()
	{
		// init var (I know this is somewhat Netlash specific)
		$project = isset($_SERVER['HTTP_HOST']) ? str_replace(array('.svn.be', '.indevelopment.be', '.local'), '', $_SERVER['HTTP_HOST']) : '';
		$domain = (isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : null;

		// create the form
		$this->frm = new SpoonForm('step2');

		// path to library
		$this->frm->addText('path_library', SpoonSession::exists('path_library') ? SpoonSession::get('path_library') : PATH_LIBRARY);

		// debug email
		$this->frm->addText('debug_email', SpoonSession::exists('spoon_debug_email') ? SpoonSession::get('spoon_debug_email') : null);

		// database configuration
		$this->frm->addText('database_hostname', SpoonSession::exists('database_hostname') ? SpoonSession::get('database_hostname') : 'localhost');
		$this->frm->addText('database_name', SpoonSession::exists('database_name') ? SpoonSession::get('database_name') : $project);
		$this->frm->addText('database_username', (SpoonSession::exists('database_username') ? SpoonSession::get('database_username') : $project), 255, 'inputText', 'inputTextError', true);
		$this->frm->addPassword('database_password', (SpoonSession::exists('database_password') ? SpoonSession::get('database_password') : null), 255, 'inputPassword', 'inputPasswordError', true);

		// default domain
		$this->frm->addText('site_domain', SpoonSession::exists('site_domain') ? SpoonSession::get('site_domain') : $domain);
		$this->frm->addText('site_title', SpoonSession::exists('site_title') ? SpoonSession::get('site_title') : $project);

		// multiple or single language
		$this->frm->addRadiobutton('languageType',	array(array('value' => 'multiple', 'label' => 'Multiple languages', 'variables' => array('multiple' => true)),
													array('value' => 'single', 'label' => 'Just one language', 'variables' => array('single' => true))), 'multiple');



		// multiple languages
		$this->frm->addMultiCheckbox('multipleLanguages', array(array('value' => 'en', 'label' => 'English'),
																array('value' => 'fr', 'label' => 'French'),
																array('value' => 'nl', 'label' => 'Dutch')), (SpoonSession::exists('languages') ? SpoonSession::get('languages') : 'nl'));


		// single languages
		$this->frm->addRadiobutton('singleLanguages', array(array('value' => 'en', 'label' => 'English'),
															array('value' => 'fr', 'label' => 'French'),
															array('value' => 'nl', 'label' => 'Dutch')), (SpoonSession::exists('site_default_language') ? SpoonSession::get('site_default_language') : 'nl'));

		// default language
		$this->frm->addRadiobutton('defaultLanguage', array(array('value' => 'en', 'label' => 'English'),
															array('value' => 'fr', 'label' => 'French'),
															array('value' => 'nl', 'label' => 'Dutch')), (SpoonSession::exists('site_default_language') ? SpoonSession::get('site_default_language') : 'nl'));


		// is the form submitted?
		if($this->frm->isSubmitted())
		{
			// path to library
			if($this->frm->getField('path_library')->isFilled('This field is required'))
			{
				if(!SpoonFile::exists($this->frm->getField('path_library')->getValue() .'/spoon/spoon.php')) $this->frm->getField('path_library')->setError('Spoon was not found in your library directory.');
			}

			// debug email address
			if($this->frm->getField('debug_email')->isFilled('This field is required.')) $this->frm->getField('debug_email')->isEmail('This is an invalid email address');

			// database settings
			$this->frm->getField('database_hostname')->isFilled('This field is required.');
			$this->frm->getField('database_name')->isFilled('This field is required.');
			$this->frm->getField('database_username')->isFilled('This field is required.');
			$this->frm->getField('database_password')->isFilled('This field is required.');

			// default domain
			$this->frm->getField('site_domain')->isFilled('This field is required.');

			// default title
			$this->frm->getField('site_title')->isFilled('This field is required.');

			// multiple languages
			if($this->frm->getField('languageType')->getValue() == 'multiple')
			{
				// list of languages
				$languages = $this->frm->getField('multipleLanguages')->getValue();

				// default language
				if(!in_array($this->frm->getField('defaultLanguage')->getValue(), $languages)) $this->frm->getField('defaultLanguage')->setError('Your default language needs to be in the list of languages you chose.');
			}

			// single language
			else
			{
				// list of languages
				$languages = (array) $this->frm->getField('singleLanguages')->getValue();

				// default language
				if(!in_array($this->frm->getField('defaultLanguage')->getValue(), $languages)) $this->frm->getField('defaultLanguage')->setError('Your default language needs to be in the list of languages you chose.');
			}


			/*
			 * Test the database connection details.
			 */
			try
			{
				// create instance
				$db = new SpoonDatabase('mysql', $this->frm->getField('database_hostname')->getValue(), $this->frm->getField('database_username')->getValue(), $this->frm->getField('database_password')->getValue(), $this->frm->getField('database_name')->getValue());

				// attempt to create table
				$db->execute('DROP TABLE IF EXISTS testtable;');
				$db->execute('CREATE TABLE IF NOT EXISTS testtable (id int(11) NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=latin1;');

				// drop table
				$db->drop('testtable');
			}

			/*
			 * Catch possible exceptions
			 */
			catch(Exception $e)
			{
				// add errors
				$this->frm->addError('Problem with database credentials');

				// show error
				$this->tpl->assign('databaseError', $e->getMessage());
			}

			// no errors?
			if($this->frm->isCorrect())
			{
				// build variables
				$variables['<spoon-debug-email>'] = $this->frm->getField('debug_email')->getValue();
				$variables['<database-name>'] = $this->frm->getField('database_name')->getValue();
				$variables['<database-hostname>'] = $this->frm->getField('database_hostname')->getValue();
				$variables['<database-username>'] = addslashes($this->frm->getField('database_username')->getValue());
				$variables['<database-password>'] = addslashes($this->frm->getField('database_password')->getValue());
				$variables['<site-domain>'] = $this->frm->getField('site_domain')->getValue();
				$variables['<site-default-title>'] = $this->frm->getField('site_title')->getValue();
				$variables['\'<site-multilanguage>\''] = ($this->frm->getField('languageType')->getValue() == 'multiple') ? 'true' : 'false';
				$variables['<path-www>'] = realpath(__FILE__ .'/../../..');
				$variables['<site-default-language>'] = $this->frm->getField('defaultLanguage')->getValue();

				// store some values in the session
				SpoonSession::set('path_library', $this->frm->getField('path_library')->getValue());
				SpoonSession::set('spoon_debug_email', $this->frm->getField('debug_email')->getValue());
				SpoonSession::set('database_hostname', $this->frm->getField('database_hostname')->getValue());
				SpoonSession::set('database_name', $this->frm->getField('database_name')->getValue());
				SpoonSession::set('database_username', $this->frm->getField('database_username')->getValue());
				SpoonSession::set('database_password', $this->frm->getField('database_password')->getValue());
				SpoonSession::set('site_domain', $this->frm->getField('site_domain')->getValue());
				SpoonSession::set('site_title', $this->frm->getField('site_title')->getValue());
				SpoonSession::set('languages', $languages);
				SpoonSession::set('site_default_language', $this->frm->getField('defaultLanguage')->getvalue());

				// globals files
				$configurationFiles = array('globals.example.php' => 'globals.php',
											'globals_frontend.example.php' => 'globals_frontend.php',
											'globals_backend.example.php' => 'globals_backend.php');

				// loop files
				foreach($configurationFiles as $sourceFilename => $destinationFilename)
				{
					// grab content
					$globalsContent = SpoonFile::getContent(PATH_LIBRARY .'/'. $sourceFilename);

					// assign the variables
					$globalsContent = str_replace(array_keys($variables), array_values($variables), $globalsContent);

					// write the file
					SpoonFile::setContent(PATH_LIBRARY .'/'. $destinationFilename, $globalsContent);
				}

				// redirect
				SpoonHTTP::redirect('index.php?step=3');
			}
		}
	}


	/**
	 * Execute step 3
	 *
	 * @return	void
	 */
	private function doStep3()
	{
		// required session variables exist
		if(!SpoonSession::exists('database_hostname', 'database_username', 'database_password', 'database_name', 'site_domain')) SpoonHTTP::redirect('index.php?step=2');

		// fetch modules
		$tmpModules = SpoonDirectory::getList(PATH_WWW .'/backend/modules', false, null, '/^[a-z0-9_]+$/i');

		// required modules
		$alwaysCheckedModules = array('core', 'locale', 'users', 'authentication', 'dashboard', 'error', 'example', 'settings',
										'pages', 'contact', 'content_blocks', 'tags');

		// manually add core to the modules
		$modules[] = array('value' => 'core', 'label' => 'Core', 'attributes' => array('disabled' => 'disabled', 'checked' => 'checked'));

		// build modules
		foreach($tmpModules as $tmpModule)
		{
			// define module
			$module = array('value' => $tmpModule, 'label' => SpoonFilter::toCamelCase($tmpModule));

			// always rquired
			if(in_array($tmpModule, $alwaysCheckedModules)) $module['attributes'] = array('disabled' => 'disabled', 'checked' => 'checked');

			// add module
			$modules[] = $module;
		}

		// create the form
		$this->frm = new SpoonForm('step3');

		// modules checkbox
		$this->frm->addMultiCheckbox('modules', $modules, $alwaysCheckedModules);

		// api email address
		$this->frm->addText('api_email');

		// password
		$this->frm->addPassword('password', null, 255, 'inputPassword', 'inputPasswordError', true);

		// smtp settings
		$this->frm->addText('smtp_server');
		$this->frm->addText('smtp_port');
		$this->frm->addText('smtp_username');
		$this->frm->addText('smtp_password');

		// is the form submitted?
		if($this->frm->isSubmitted())
		{
			// validate
			if($this->frm->getField('api_email')->isFilled('Field is required.')) $this->frm->getField('api_email')->isEmail('No valid email address.');
			$this->frm->getField('password')->isFilled('Field is required.');
			$this->frm->getField('smtp_server')->isFilled('Field is required.');
			$this->frm->getField('smtp_port')->isFilled('Field is required.');
			$this->frm->getField('smtp_username')->isFilled('Field is required.');
			$this->frm->getField('smtp_password')->isFilled('Field is required.');

			// no errors?
			if($this->frm->isCorrect())
			{
				// @later dit moet proper opgelost worden.
				SpoonSession::set('password', $this->frm->getField('password')->getValue());

				// nasty shit
				if(!isset($_POST['modules'])) $_POST['modules'] = array();

				// modules to install?
				$modulesToInstall = array_merge($alwaysCheckedModules, (array) $this->frm->getField('modules')->getValue());

				// database instance
				$db = new SpoonDatabase('mysql', SpoonSession::get('database_hostname'), SpoonSession::get('database_username'), SpoonSession::get('database_password'), SpoonSession::get('database_name'));

				// utf8 compliance & MySQL-timezone
				$db->execute('SET CHARACTER SET utf8, NAMES utf8, time_zone = "+0:00";');

				/**
				 * First we need to install the core. All the linked modules, settings and or sql tables are
				 * being installed.
				 */
				require_once PATH_WWW .'/backend/core/installer/install.php';

				// install the core
				$install = new CoreInstall($db, SpoonSession::get('languages'),
											array('default_language' => SpoonSession::get('site_default_language'),
													'spoon_debug_email' => SpoonSession::get('spoon_debug_email'),
													'api_email' => $this->frm->getField('api_email')->getValue(),
													'site_domain' => SpoonSession::get('site_domain'),
													'site_title' => SpoonSession::get('site_title'),
													'smtp_server' => $this->frm->getField('smtp_server')->getValue(),
													'smtp_port' => $this->frm->getField('smtp_port')->getValue(),
													'smtp_username' => $this->frm->getField('smtp_username')->getValue(),
													'smtp_password' => $this->frm->getField('smtp_password')->getValue()));

				// modules were selected
				if(!empty($modulesToInstall))
				{
					// loop all modules
					foreach($modulesToInstall as $module)
					{
						// skip core
						if($module == 'core') continue;

						// install exists
						if(SpoonFile::exists(PATH_WWW .'/backend/modules/'. $module .'/installer/install.php'))
						{
							// init var
							$variables = array(); // @later variabelen meegeven, moet op een andere manier gebeuren.
							if($module == 'users') $variables['password'] = $this->frm->getField('password')->getValue();

							// load file
							require_once PATH_WWW .'/backend/modules/'. $module .'/installer/install.php';

							// class name
							$class = SpoonFilter::toCamelCase($module) .'Install';

							// execute installer
							$install = new $class($db, SpoonSession::get('languages'), $variables);
						}
					}
				}

				// generate locale
				foreach(SpoonSession::get('languages') as $language)
				{
					$this->buildCache($db, $language, 'frontend');
					$this->buildCache($db, $language, 'backend');
				}

				// update installation status
				SpoonSession::set('installed', true);

				// go to step 4
				SpoonHTTP::redirect('index.php?step=4');
			}
		}
	}


	/**
	 * Execute step 4
	 *
	 * @return	void
	 */
	private function doStep4()
	{
		// validate
		if(!SpoonSession::exists('installed')) SpoonHTTP::redirect('index.php?step=2');

		// assign variables
		$this->tpl->assign('username', SpoonSession::get('spoon_debug_email'));
		$this->tpl->assign('password', SpoonSession::get('password'));

		// write file
		SpoonFile::setContent(PATH_WWW .'/install/installed.txt', 'installation complete '. date('Y-m-d H:i:s'));
	}


	/**
	 * Initialize some constants and variables
	 *
	 * @return	void
	 */
	private function init()
	{
		// @later tijs - waarom geen glob gebruikt? http://be.php.net/glob

		// @later davy - van zodra de PATH_LIBRARY in de session zit, moet ge die gebruiken

		// @later davy - in de init zou hij het nieuwe pad naar library moeten gebruiken

		// get the www path
		define('PATH_WWW', realpath(str_replace('/install/engine/install.php', '', __FILE__)));

		// calculate the homefolder
		$homeFolder = realpath(PATH_WWW .'/..');

		// attempt to open directory
		$directory = @opendir($homeFolder);

		// do your thing if directory-handle isn't false
		if($directory !== false)
		{
			// start reading
			while((($folder = readdir($directory)) !== false))
			{
				// no '.' and '..' and it's a file
				if(($folder != '.') && ($folder != '..'))
				{
					// directory
					if(is_dir($homeFolder .'/'. $folder .'/spoon'))
					{
						// init var
						$matches = array();

						// get content
						$fileContent = file_get_contents($homeFolder .'/'. $folder .'/spoon/spoon.php');

						// try to get the version
						preg_match('/SPOON_VERSION\',\s\'(.*)\'/', $fileContent, $matches);

						// no matches
						if(!isset($matches[1])) continue;

						// matches found
						else
						{
							// get the version
							$version = (int) str_replace('.', '', $matches[1]);

							// validate the version
							if($version <= 120)
							{
								// set Spoon path
								define('PATH_LIBRARY', $homeFolder .'/'. $folder);

								// stop looking arround
								break;
							}
						}
					}
				}
			}
		}

		// close directory
		@closedir($directory);

		// validate
		if(!defined('PATH_LIBRARY'))
		{
			echo 'We are unable to find the spoon directory. Make sure it exists in your library folder';
			exit;
		}

		// store in variables
		$this->variables['PATH_WWW'] = PATH_WWW;
		$this->variables['PATH_LIBRARY'] = PATH_LIBRARY;

		// set include path
		set_include_path(PATH_LIBRARY . PATH_SEPARATOR . get_include_path());

		// define some constants
		define('SPOON_DEBUG', true);

		// require spoon
		require_once 'spoon/spoon.php';

		// get spoon version
		$version = (int) str_replace('.', '', SPOON_VERSION);

		// validate version
		if($version < 120)
		{
			echo 'Can\'t find Spoon. Make sure their is a folder containing spoon on the same level as the document_root';
			exit;
		}

		// already installed
		if(file_exists(PATH_WWW .'/install/installed.txt'))
		{
			echo 'Fork CMS has already been installed.';
			exit;
		}
	}


	/**
	 * Store a setting
	 *
	 * @return	void
	 * @param	string $module
	 * @param	string $name
	 * @param	string $value
	 */
	private function storeSetting($module, $name, $value)
	{
		// redefine
		$module = (string) $module;
		$name = (string) $name;
		$value = serialize($value);

		// create db connection
		$db = new SpoonDatabase('mysql',
								SpoonSession::get('database_hostname'),
								SpoonSession::get('database_username'),
								SpoonSession::get('database_password'),
								SpoonSession::get('database_name'));

		// store the keys
		$db->execute('INSERT INTO modules_settings(module, name, value)
						VALUES(?, ?, ?)
						ON DUPLICATE KEY UPDATE value = ?;',
						array($module, $name, $value, $value));
	}

}

?>