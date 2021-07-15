<?php
use Auth0\SDK\Exception\InvalidTokenException;
use Auth0\SDK\Helpers\JWKFetcher;
use Auth0\SDK\Helpers\Tokens\AsymmetricVerifier;
use Auth0\SDK\Helpers\Tokens\TokenVerifier;

$app->on('cockpit.api.authenticate', function($data) use($app) {

    if ( $data['token'] ) {
        
        if ($data['resource'] == 'auth0') {
            $user = $this->module('auth0')->userinfo($data['token'], [
                'normalize' => true,
                'cache'     => $app->retrieve('config/auth0/cache', false)
            ]);
    
            if ($user) {
                $data['authenticated'] = true;
                $data['user'] = $user;
            }
            
        } else {
            
            // Authenticate token scope 
            // TODO - cache
            
            try {
                $accessToken = $data['token'];
                
                $issuer = 'https://' . $app->module('auth0')->getDomain() . '/';
                $audience = $app->module('auth0')->getAudience();
                
                $jwksFetcher = new JWKFetcher();
                $jwks        = $jwksFetcher->getKeys($issuer . '.well-known/jwks.json');
                $sigVerifier = new AsymmetricVerifier( $jwks );
                
                $tokenVerifier = new TokenVerifier($issuer, $audience, $sigVerifier);
                $decoded = $tokenVerifier->verify( $accessToken );
                
                if ( $decoded ) {
                    
                    $permissions = $decoded['permissions'];
                    
                    // TODO - permissions
                
                    $data['authenticated'] = true;
                }
                
            } catch (\Exception $e) {
                $this->stop([ 'error' => $e->getMessage() ], 401);
            }
            
        }
    }
    
});

$app->on('cockpit.rest.init', function($routes) use($app) {
  $routes['auth0'] = 'Auth0\\Controller\\RestApi';
});
