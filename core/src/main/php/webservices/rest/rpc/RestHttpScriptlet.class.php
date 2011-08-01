<?php
/* This class is part of the XP framework
 *
 * $Id$
 */

  uses(
    'scriptlet.HttpScriptlet',
    'webservices.rest.transport.HttpRequestAdapterFactory',
    'webservices.rest.transport.HttpResponseAdapterFactory'
  );
  
  /**
   * REST HTTP scriptlet
   *
   * @test    xp://net.xp_framework.unittest.scriptlet.HttpScriptletProcessTest
   */
  class RestHttpScriptlet extends HttpScriptlet {
    protected $router= NULL;
    
    /**
     * Constructor
     * 
     * @param string package The package containing handler classes
     * @param string router The router to use
     * @param string base The base URL (will be stripped off from request url)
     */
    public function __construct($package, $router, $base= '') {
      $this->router= XPClass::forName($router)->newInstance();
      $this->router->configure($package, $base);
    }
    
    /**
     * Do request processing
     * 
     * @param scriptlet.http.HttpScriptletRequest request The request
     * @param scriptlet.http.HttpScriptletResponse response The response
     */
    public function doProcess($request, $response) {
      $req= HttpRequestAdapterFactory::forRequest($request)->newInstance($request);
      $res= HttpResponseAdapterFactory::forRequest($request)->newInstance($response);
      
      $routes= $this->router->routesFor($req, $res);
      
      if (sizeof($routes) == 0)  throw new IllegalStateException(
        'Can not route request '.$req->getPath()
      );
      
      $res->setData(current($routes)->route($req, $res));
    }
    
    /**
     * Calculate method to invoke
     *
     * @param   scriptlet.HttpScriptletRequest request 
     * @return  string
     */
    public function handleMethod($request) {
      
      // Setup request
      parent::handleMethod($request);
      
      // We want to handle all request at one place
      return 'doProcess';
    }
  }
?>
