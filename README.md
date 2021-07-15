A fork of [joona/CockpitCMS-Auth0](https://github.com/joona/CockpitCMS-Auth0).

Updated to work on the newest version of [Cockpit CMS](https://github.com/agentejo/cockpit) (v0.12.1).

## Additional Features / Tweaks

- Role Groups: Allows you to specify Cockpit group names for Auth0 roles.
- Login: Auth0 logo removed & title replaced with Cockpit "app.name"


## Setup

1. Download repository and place files in a new folder called "auth0"
2. Upload "auth0" folder to "/{COCKPIT_ROOT}/modules/"
3. Edit "/{COCKPIT_ROOT}/config/config.php" - using the below example for reference

#### Using Auth0 Roles (Multiple User Types)

4. Set "use_roles" to true.
5. Create a new Auth0 rule using the code at the bottom of this article to ensure roles are included when logging in.
6. Configure the "role_groups" option to include your new role (Format: 'AUTH0_ROLE_NAME' => 'COCKPIT_ROLE_NAME')

#### Without Roles (Single User Type)

4. Set "use_roles" to false.
5. Set "default_group" to the Cockpit group you would like all users to be assigned to.
6. Configure your Cockpit group by adding it's configuration to the core "groups" setting - see below configuration for example.


## Configuration

In the Cockpit configuration (`config/config.php`), place the following:

```
'auth0' => [
    'enabled'           => true,

    // App Settings
    'domain'            => 'my-app.auth0.com',                  // Auth0 Domain
    'id'                => 'APP_ID',                            // App Client ID
    'scope'             => 'openid profile email read:roles',   // Scopes for Cockpit Admin login to use
    'cache'             => true,                                // Use cache?
    'session_ttl'       => 10*24*60,                            // TTL
    'namespace'         => 'https://my-namespace.com',          // App Namespace

    // API Settings
    'audience'          => 'api.iconica.com.au',                // API Identifier

    // Optional Lock Options
    'secret'            => '', // Auth0 Secret
    'lock_options'      => [], // Array of Auth0 Lock options - see https://auth0.com/docs/libraries/lock/lock-configuration
    'theme'             => [
        'logo'  => 'https://my-app.com/path/to/logo.png'
    ],

    // Roles / Groups
    'default_group'     => 'auth0user',
    'use_roles'         => true,
    'role_groups'       => [
        'Backend'   => 'admin'
    ]
],

'groups' => [

    'auth0user' => [
        '$admin'    => false,
        '$vars'     => [
            'finder.path' => '/upload'
        ],
        'cockpit'   => [
            'backend'   => true,
            'finder'    => true
        ]
    ]

 ]
```

## Roles

You will then need to add the following rule to Auth0 to allow the Roles to be exposed when fetching user data:

```
function (user, context, callback) {
    if (context.clientID === 'CLIENTID') {
        const namespace = 'https://NAMESPACE';
        context.idToken[namespace] = {
            roles: (context.authorization || {}).roles
        };
    }
    callback(null, user, context);
}
```
To make this work, ensure the `read:roles` scope is listed in your authorization scope (see example config above).
You also need to add your namespace to the configuration so that plugin knows where to read namespaced information.

### Role Groups

You can then set the "role_groups" setting to let Cockpit know which Auth0 Roles to assign groups for.

For example, if you created a role called "Backend" in Auth0, the following will add any users with this role to the "admin" group.

```
'role_groups' => [
    'Backend'   => 'admin'
]
```

## Note on user accounts

To make document author associations to work, this plugin creates copy of the Auth0 user automatically to the accounts.
This user won't have a password, and will have email prefixed with `auth0:$email`, so loggin in with these generated accounts is not possible without Auth0.

Generated users will also have additional `generated` flag in the user document.
