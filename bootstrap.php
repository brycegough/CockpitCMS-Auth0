<?php

function err() {
  if(!getenv('AUTH0_DEBUG')) return;
  $args = func_get_args();
  $str = join(' ', $args);
  error_log($str);
}

$this->module('auth0')->extend([
    
    /*
     * Get or create a user via Auth0
     */
  'getOrCreateUser' => function($info) use($app) {
    if(!isset($info['sub'])) return null;
    if(!isset($info['email'])) return null;

    $maybeUser = $this->app->storage->findOne('cockpit/accounts', ['user' => $info['sub']]);
    err("Got cockpit user:", var_export($maybeUser, true));

    if(!$maybeUser) {
      err("User not found with id", var_export($maybeUser, true));

      $now = time();
      $emailParts = explode('@', $info['email']);
      $username = $emailParts[0];

      $user = [
        '_modified' => $now,
        '_created' => $now,
        'user'   => $info['sub'],
        'name' => $info['name'],
        'email'  => 'auth0:'.$info['email'],
        'active' => true,
        'group'  => $app->module('auth0')->getDefaultGroup(),
        'i18n'   => $app->helper('i18n')->locale,
        'auth0'  => $info['sub'],
        'generated' => true
      ];

      if(isset($info['locale'])) {
        //$user['i18n'] = $info['locale'];
      }

      $this->app->storage->insert('cockpit/accounts', $user);
      err("Auth0 user added:", var_export($user, true));
      $maybeUser = $user;
      $maybeUser['_fresh'] = true;
    }

    return $maybeUser;
  },
    
    'getDomain' => function() use($app) {
        return $app->retrieve('config/auth0/domain', false);  
    },
    
    'getNamespace' => function() use($app) {
        $domain = $app->module('auth0')->getDomain();
        return $app->retrieve('config/auth0/namespace', 'https://'.$domain);  
    },
    
    'getRoleGroups' => function() use($app) {
        return $app->retrieve('config/auth0/role_groups', []);  
    },
    
    'getDefaultGroup' => function() use($app) {
        return $app->retrieve('config/auth0/default_group', 'auth0user');
    },

    /*
     * Retrieve User Information
     */
  'userinfo' => function($token, $options = []) use($app) {
    $options = array_merge([
      'normalize' => false,
      'cache' => false,
      'use_roles' => true
    ], $options);

    $domain = $app->module('auth0')->getDomain();
    $namespace = $app->module('auth0')->getNamespace();

    $info = $this->app->helper('cache')->read("auth0.user.{$domain}.{$token}", null);

    if (!$info) {
        $ch = curl_init('https://'.$domain.'/userinfo');

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Content-type: application/json"
        ]);

        $result = curl_exec($ch);
        err("Auth0 response", var_export($result, true));

        if(!$result) {
            trigger_error(curl_error($ch));
            return null;
        }
        curl_close($ch);

        $info = json_decode($result, true);
      
    } elseif (!empty($result)) {
        err("Got cached info", var_export($result, true));
    }

    if(!empty($info['error'])) {
      err("Auth0 Error", var_export($info, true));
      $this->app->helper('cache')->write("auth0.user.{$domain}.{$token}", null, $options['cache']);
      return null;
    }


    if ($info && $options['cache']) {
      $this->app->helper('cache')->write("auth0.user.{$domain}.{$token}", $info, $options['cache']);
    }

    if ($info && $options['normalize']) {
        $userGroup = $app->module('auth0')->getDefaultGroup();

        if ($options['use_roles']) {
            
            $roles = $info[$namespace]['roles'] ?? [];
            $role_groups = $app->module('auth0')->getRoleGroups();
            
            if (is_array($roles) && is_array($role_groups)) {
                foreach ($roles as $role) {
                    if ( isset( $role_groups[ $role ] ) ) {
                        $userGroup = $role_groups[ $role ];
                    }
                }
            }
            
        }
      
      // get or create cockpit account for user
      $cockpitUser = $app->module('auth0')->getOrCreateUser($info);

      $user = [
        '_id'   => $cockpitUser['_id'],
        'name'  => $info['name'],
        'email' => $info['email'],
        'group' => $userGroup
      ];

      $user['auth0'] = $info;
      $user['cockpit_user'] = $cockpitUser;
      
      $info = $user;
    }


    $info['auth0token'] = $token;

    err("Userinfo", var_export($info, true));
    return $info;
  }
]);

/*
 * If module is not enabled, we're done here
 */
if (!$app->retrieve('config/auth0/enabled')) {
  return;
}

$app('acl')->addResource('cockpit', [
  'backend', 
  'finder', 
  'accounts', 
  'settings', 
  'rest', 
  'webhooks', 
  'info'
]);

// override views
$app->path('cockpit', __DIR__.'/cockpit');

$app->on('cockpit.bootstrap', function() use($app) {
  $app('session')->init();
});


// Include REST API
require_once(__DIR__ . '/Controller/RestApi.php');

if (COCKPIT_API_REQUEST) {
  include_once(__DIR__.'/api.php');
} elseif (COCKPIT_ADMIN) {
  include_once(__DIR__.'/admin.php');
}
