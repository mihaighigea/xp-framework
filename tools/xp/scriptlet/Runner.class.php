<?php
/* This class is part of the XP framework
 *
 * $Id$ 
 */

  $package= 'xp.scriptlet';
  uses(
    'util.PropertyManager',
    'rdbms.ConnectionManager'
  );
  
  /**
   * Scriptlet runner
   *
   * @test      xp://net.xp_framework.unittest.scriptlet.RunnerTest
   * @purpose   Scriptlet runner
   */
  class xp�scriptlet�Runner extends Object {
    const
      XML         = 0x0001,
      ERRORS      = 0x0002,
      STACKTRACE  = 0x0004,
      TRACE       = 0x0008;
      
    protected
      $flags      = 0x0000,
      $webroot    = NULL,
      $conf       = NULL,
      $scriptlet  = NULL;
    
    public static function main(array $args) {
      try {
        $this->webroot= $args[0];

        // This is not using the PropertyManager by intention: we'll postpone the
        // initialization of it until later, because there might be configuration
        // that indicates to use another properties directory.
        $self= new self(new Properties($this->webroot.'/etc/web.ini'));

        $self->setup();
      } catch (Throwable $t) {
        header('HTTP/1.0 500 Scriptlet setup failed; very sorry.');
        throw $t;
      }
      
      try {
        $self->run();
      } catch (TargetInvocationException $e) {
        headers_sent() || header('HTTP/1.0 500 Internal server error');
        throw $e->getCause();
      }
    }

    /**
     * Constructor
     *
     * @param   scriptlet.HttpScriptlet scriptlet
     */
    public function __construct(Properties $conf) {
      $this->conf= $conf;
    }

    /**
     * Find application name that has a mapping given which fits
     *
     * @param   util.Hashmap map
     * @param   string url
     * @return  mixed string or NULL if no match was found
     */
    public static function findApplication(Hashmap $map, $url) {
      foreach ($map->keys() as $pattern) {
        if (preg_match('#'.preg_quote($pattern, '#').'#', $url)) return $map->get($pattern);
      }

      return NULL;
    }

    protected function setup() {
      $url= getenv('SCRIPT_URL');
      $specific= getenv('SERVER_PROFILE');

      $mappings= $this->conf->readHash('app', 'mappings');
      if (!$mappings instanceof Hashmap)
        throw new IllegalStateException('Application misconfigured: "app" section missing or broken.');

      if (NULL === ($app= self::findApplication($mappings, $url))) {
        throw new IllegalArgumentException('Could not find app responsible for request to '.$url);
      }

      // Load and configure scriptlet
      $scriptlet= 'app::'.$app;

      try {
        $class= XPClass::forName($this->readString($specific, $scriptlet, 'class'));
      } catch (ClassNotFoundException $e) {
        throw new IllegalArgumentException('Scriptlet "'.$scriptlet.'" misconfigured or missing: '.$e->getMessage());
      }
      $args= array();
      foreach ($this->readArray($specific, $scriptlet, 'init-params') as $value) {
        $args[]= strtr($value, array('{WEBROOT}' => $this->webroot));
      }

      // Set environment variables
      $env= $this->readHash($specific, $scriptlet, 'init-envs', new HashMap());
      foreach ($env->keys() as $key) {
        putenv($key.'='.$env->get($key));
      }

      // Configure PropertyManager
      $pm= PropertyManager::getInstance();
      $pm->configure(strtr($this->readString($specific, $scriptlet, 'prop-base', $this->webroot.'/etc'), array('{WEBROOT}' => $this->webroot)));

      // Always configure Logger (prior to ConnectionManager, so that one can pick up
      // categories from Logger)
      $pm->hasProperties('log') && Logger::getInstance()->configure($pm->getProperties('log'));

      // Always make connection manager available
      $pm->hasProperties('database') && ConnectionManager::getInstance()->configure($pm->getProperties('database'));

      $this->setScriptlet($class->hasConstructor()
        ? $class->getConstructor()->newInstance($args)
        : $class->newInstance()
      );

      // Determine debug level
      foreach ($this->readArray($specific, $scriptlet, 'debug', array()) as $lvl) {
        $this->flags|= $this->getClass()->getConstant($lvl);
      }
    }
    
    /**
     * Set scriptlet to run
     *
     * @param   scriptlet.HttpScriptlet scriptlet
     */
    public function setScriptlet(HttpScriptlet $scriptlet) {
      $this->scriptlet= $scriptlet;
    }
    
    /**
     * Run scriptlet instance
     *
     */
    protected function run() {
      $exception= NULL;
      if ($this->flags & self::TRACE && $this->scriptlet instanceof Traceable) {
        $this->scriptlet->setTrace(Logger::getInstance()->getCategory('scriptlet'));
      }
      
      try {
        $this->scriptlet->init();
        $response= $this->scriptlet->process();
      } catch (HttpScriptletException $e) {

        // Remember this exception to show it below the error page,
        // if this flag was set
        $exception= $e;

        // TODO: Instead of checking for a certain method, this should
        // check if the scriptlet class implements a certain interface
        if (is_callable(array($this->scriptlet, 'fail'))) {
          $response= $this->scriptlet->fail($e);
        } else {
          $response= $e->getResponse();
          $this->except($response, $e);
        }
      }

      // Send output
      if (!$response->headersSent()) $response->sendHeaders();
      $response->sendContent();
      flush();

      // Call scriptlet's finalizer
      $this->scriptlet->finalize();
      
      if (
        ($this->flags & self::XML) &&
        ($response && isset($response->document))
      ) {
        echo '<xmp>', $response->document->getDeclaration()."\n".$response->document->getSource(0), '</xmp>';
      }
      
      if (($this->flags & self::ERRORS)) {
        echo
          '<xmp>',
          $exception instanceof Throwable ? $exception->toString() : '',
          var_export(xp::registry('errors'), 1),
          '</xmp>'
        ;
      }
    }
    
    /**
     * Handle exception from scriptlet
     *
     * @param   scriptlet.HttpScriptletResponse response
     * @param   lang.Throwable e
     */
    protected function except(HttpScriptletResponse $response, Throwable $e) {
      $errorPage= ($this->getClass()->getPackage()->providesResource('error'.$response->statusCode.'.html')
        ? $this->getClass()->getPackage()->getResource('error'.$response->statusCode.'.html')
        : $this->getClass()->getPackage()->getResource('error500.html')
      );
      $response->setContent(str_replace(
        '<xp:value-of select="reason"/>',
        (($this->flags & self::STACKTRACE)
          ? $e->toString()
          : $e->getMessage()
        ),
        $errorPage
      ));
    }

    /**
     * Read string. First tries special section "section"@"specific", then defaults 
     * to "section"
     *
     * @param   string specific
     * @param   string section
     * @param   string key
     * @param   var default default NULL
     * @return  string
     */
    protected function readString($specific, $section, $key, $default= NULL) {
      return $this->conf->readString($section.'@'.$specific, $key, $this->conf->readString($section, $key, $default));
    }
    
    /**
     * Read array. First tries special section "section"@"specific", then defaults 
     * to "section"
     *
     * @param   util.Properties pr
     * @param   string specific
     * @param   string section
     * @param   string key
     * @param   var default default NULL
     * @return  string
     */
    protected function readArray($specific, $section, $key, $default= NULL) {
      return $this->conf->readArray($section.'@'.$specific, $key, $this->conf->readArray($section, $key, $default));
    }
    
    /**
     * Read hashmap. First tries special section "section"@"specific", then defaults 
     * to "section"
     *
     * @param   util.Properties pr
     * @param   string specific
     * @param   string section
     * @param   string key
     * @param   var default default NULL
     * @return  string
     */
    protected function readHash($specific, $section, $key, $default= NULL) {
      return $this->conf->readHash($section.'@'.$specific, $key, $this->conf->readHash($section, $key, $default));
    }
  }
?>
