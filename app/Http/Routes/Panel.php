<?php

/**
 * Control panel, authentication, board configuration, site administration.
 *
 * @category   Routes
 *
 * @author     Joshua Moon <josh@jaw.sh>
 * @copyright  2016 Infinity Next Development Group
 * @license    http://www.gnu.org/licenses/agpl-3.0.en.html AGPL3
 *
 * @since      0.6.0
 */

/**
 * Authentication
 */
// logout
Route::get('logout',   ['as' => 'logout', 'uses' => 'AuthController@getLogout',]);
// login
Route::get('login',    ['as' => 'login', 'uses' => 'AuthController@getLogin',]);
// login attempt
Route::post('login',   ['as' => 'login.attempt', 'uses' => 'AuthController@postLogin',]);
// register
Route::get('register', ['as' => 'register', 'uses' => 'AuthController@getRegister',]);
// register create
Route::put('register', ['as' => 'register.create', 'uses' => 'AuthController@putRegister',]);

/**
 * Account Recovery
 */
Route::group(['prefix' => 'password', 'as' => 'password',], function() {
    // password
    Route::get('/', ['uses' => 'PasswordController@getIndex',]);
    // password update
    Route::post('/',['as' => '.update', 'uses' => 'PasswordController@postIndex',]);
    // reset
    Route::get('reset/{token?}', ['as' => '.reset', 'uses' => 'PasswordController@getReset',]);
    // reset attempt
    Route::post('reset',['as' => '.reset.attempt', 'uses' => 'PasswordController@postReset',]);
    // email
    Route::get('email', ['as' => '.email', 'uses' => 'PasswordController@getEmail',]);
    // email request
    Route::post('email',['as' => '.email.request', 'uses' => 'PasswordController@postEmail',]);
});

Route::group(['middleware' => [ \App\Http\Middleware\BoardAmbivilance::class,],], function () {
    /**
     * Bans and Appeals
     */
    Route::group(['prefix' => 'bans',], function () {
        Route::get('/', ['as' => 'site.bans', 'uses' => 'BansController@getIndex']);

        Route::get('banned', ['as' => 'banned', 'uses' => 'BansController@getIndexForSelf']);

        Route::get('board/{board}', ['as' => 'board.bans', 'uses' => 'BansController@getBoardIndex']);

        Route::get('board/{board}/{ban}', ['as' => 'board.ban', 'uses' => 'BansController@getBan']);
        Route::put('board/{board}/{ban}', ['as' => 'board.ban.appeal', 'uses' => 'BansController@putAppeal']);

        Route::get('global/{ban}', ['as' => 'global.ban', 'uses' => 'BansController@getBan']);
        Route::put('global/{ban}', ['as' => 'global.ban.appeal', 'uses' => 'BansController@putAppeal']);
    });

    /**
     * Post History
     */
    Route::get('history/{ip}', ['as' => 'history.global', 'uses' => 'HistoryController@list_history',]);

    /*
     *  Page Controllers (Panel / Management)
     */
    Route::get('site/pages', ['as' => 'site.pages', 'uses' => 'PageController@index']);
    Route::get('site/page/{page}/delete', ['as' => 'site.page.delete', 'uses' => 'PageController@delete',]);
    Route::resource('site/page', 'PageController', [
        'names' => [
            'index' => 'site.page.index',
            'create' => 'site.page.create',
            'store' => 'site.page.store',
            'show' => 'site.page.show',
            'edit' => 'site.page.edit',
            'update' => 'site.page.update',
            'destroy' => 'site.page.destroy',
        ],
    ]);
    Route::get('board/{board}/page/{page}/delete', [
        'as' => 'board.page.delete',
        'uses' => 'PageController@delete',
    ]);
    Route::get('board/{board}/pages', ['as' => 'board.pages', 'uses' => 'PageController@index']);
    Route::resource('board/{board}/page', 'PageController', [
        'names' => [
            'index' => 'board.page.index',
            'create' => 'board.page.create',
            'store' => 'board.page.store',
            'show' => 'board.page.show',
            'edit' => 'board.page.edit',
            'update' => 'board.page.update',
            'destroy' => 'board.page.destroy',
        ],
    ]);
});

/**
 * Board Controls
 */
Route::group(['namespace' => 'Boards',], function () {
    /**
     * Board Lists
     */
    Route::group(['as' => 'boards.', 'prefix' => 'boards',], function () {
        Route::get('create', ['as' => 'create', 'uses' => 'BoardsController@getCreate']);
        Route::put('create', ['as' => 'store', 'uses' => 'BoardsController@putCreate']);

        Route::get('assets', ['as' => 'assets', 'uses' => 'BoardsController@getAssets']);
        Route::get('config', ['as' => 'config', 'uses' => 'BoardsController@getConfig']);
        Route::get('staff',  ['as' => 'staff', 'uses' => 'BoardsController@getStaff']);
        Route::get('tags',   ['as' => 'tags', 'uses' => 'BoardsController@getTags']);

        Route::get('/', ['as' => 'index', 'uses' => 'BoardsController@getIndex']);
    });

    /**
     * Ban Appeals
     */
    Route::get('appeals/{board?}', ['as' => 'appeals.index', 'uses' => 'AppealsController@getIndex']);
    Route::patch('appeals/{board?}', ['as' => 'appeals.patch', 'uses' => 'AppealsController@patchIndex']);

    /**
     * Reports
     */
    Route::group(['as' => 'reports.', 'prefix' => 'reports'], function () {
        Route::get('{report}/dismiss', ['as' => 'dismiss', 'uses' => 'ReportsController@getDismiss']);
        Route::get('{report}/dismiss-ip', ['as' => 'dismiss.ip', 'uses' => 'ReportsController@getDismissIp']);
        Route::get('{post}/dismiss-post', ['as' => 'dismiss.post', 'uses' => 'ReportsController@getDismissPost']);
        Route::get('{report}/promote', ['as' => 'promote', 'uses' => 'ReportsController@getPromote']);
        Route::get('{post}/promote-post', ['as' => 'promote.post', 'uses' => 'ReportsController@getPromotePost']);
        Route::get('{report}/demote', ['as' => 'demote', 'uses' => 'ReportsController@getDemote']);
        Route::get('{post}/demote-post', ['as' => 'demote.post', 'uses' => 'ReportsController@getDemotePost']);
        Route::get('/', ['as' => 'index', 'uses' => 'ReportsController@getIndex']);
    });

    Route::group(['prefix' => 'board/{board}', 'as' => 'board.',], function () {
        /*
         * Board Featuring
         */
        Route::get('feature',  ['as' => 'feature', 'uses' => 'FeatureController@getIndex',]);
        Route::post('feature', ['as' => 'feature.update', 'uses' => 'FeatureController@postIndex',]);

        /**
         * Roles
         */
        Route::get('roles/create',  ['as' => 'roles.create', 'uses' => 'RolesController@create',]);
        Route::put('roles',         ['as' => 'roles.store', 'uses' => 'RolesController@store',]);
        Route::get('roles',         ['as' => 'roles', 'uses' => 'RolesController@get',]);

        /**
         * Roles
         */
        Route::get('role/{role}/delete',        ['as' => 'role.delete', 'uses' => 'RoleController@getDelete',]);
        Route::delete('role/{role}/delete',     ['as' => 'role.destroy', 'uses' => 'RoleController@destroyDelete',]);
        Route::patch('role/{role}',             ['as' => 'role.store', 'uses' => 'RoleController@patchIndex',]);
        Route::get('role/{role}',               ['as' => 'role.show', 'uses' => 'RoleController@getIndex',]);

        /**
         * Permissions
         */
        Route::get('role/{role}/permissions',   ['as' => 'role.permissions', 'uses' => 'PermissionController@getPermissions',]);
        Route::patch('role/{role}/permissions', ['as' => 'role.permissions.patch', 'uses' => 'PermissionController@patchPermissions',]);

        /**
         * Staff Management
         */
        Route::group(['prefix' => 'staff'], function()
        {
            Route::get('create',               ['as' => 'staff.create', 'uses' => 'StaffController@create',]);
            Route::get('{user}/delete', ['as' => 'staff.delete', 'uses' => 'StaffController@delete',]);
            Route::delete('{user}',     ['as' => 'staff.destroy', 'uses' => 'StaffController@destroy',]);
            Route::get('{user}',        ['as' => 'staff.show', 'uses' => 'StaffController@show',]);
            Route::patch('{user}',             ['as' => 'staff.update', 'uses' => 'StaffController@patch',]);
            Route::put('/',                    ['as' => 'staff.store', 'uses' => 'StaffController@store',]);
            Route::get('/',                    ['as' => 'staff', 'uses' => 'StaffController@index',]);
        });

        /**
         * Tags
         */
         Route::get('tags',      ['as' => 'tags', 'uses' => 'ConfigController@getTags',]);
         Route::put('tags',      ['as' => 'tags.put', 'uses' => 'ConfigController@putTags',]);

         /**
          * Assets
          */
         Route::get('assets',     ['as' => 'assets', 'uses' => 'ConfigController@getAssets',]);
         Route::patch('assets',   ['as' => 'assets.patch', 'uses' => 'ConfigController@patchAssets',]);
         Route::put('assets',     ['as' => 'assets.put', 'uses' => 'ConfigController@putAssets',]);
         Route::post('assets',    ['as' => 'assets.destroy', 'uses' => 'ConfigController@destroyAssets',]);

        /**
         * Config
         */
         Route::get('config',    ['as' => 'config', 'uses' => 'ConfigController@getConfig',]);
         Route::patch('config',  ['as' => 'config.patch', 'uses' => 'ConfigController@patchConfig',]);
         Route::get('/',         ['as' => 'basic', 'uses' => 'ConfigController@getConfig',]);
         Route::patch('/',       ['as' => 'basic.patch', 'uses' => 'ConfigController@patchConfig',]);
    });
});

/**
 * Admin functionality
 */
Route::group(['namespace' => 'Site', 'as' => 'site.', 'prefix' => 'site',], function () {
    /**
     * Config
     */
    // config list
    Route::get('config', ['as' => 'config', 'uses' => 'ConfigController@get']);
    // config patch
    Route::patch('config', ['as' => 'config.edit', 'uses' => 'ConfigController@patch']);

    /**
     * Utilities
     */
    // phpinfo
    Route::get('phpinfo', ['as' => 'phpinfo', 'uses' => 'SiteController@getPhpinfo']);


    // site dashboard
    Route::get('/', ['as' => 'index', 'uses' => 'SiteController@getIndex',]);
});

/**
 * Users
 */
Route::group(['as' => 'user.', 'namespace' => 'Users', 'prefix' => 'user',], function () {
    // user show
    Route::get('{user}/{slug?}', ['as' => 'show', 'uses' => 'UserController@show']);

    // users list
    Route::get('/', ['as' => 'index', 'uses' => 'UserController@index']);
});

/**
 * Global Roles
 */
Route::group(['as' => 'role.', 'namespace' => 'Roles', 'prefix' => 'role',], function () {
    Route::get('{role}', ['as' => 'show', 'uses' => 'RolesController@show',]);

    /**
     * Permissions
     */
    Route::group(['as' => 'permission.', 'prefix' => 'permissions/{role}'], function () {
        // permissions
        Route::patch('/', ['as' => 'patch', 'uses' => 'PermissionsController@patch',]);
        Route::get('/', ['as' => 'index', 'uses' => 'PermissionsController@index',]);
    });

    // role index
    Route::get('/', ['as' => 'index', 'uses' => 'RolesController@index',]);
});

/**
 * Site Adventure
 */
// adventure to random board
Route::get('adventure', ['as' => 'adventure', 'uses' => 'AdventureController@get']);

/**
 * Landing page
 */
// home
Route::get('/', ['as' => 'home', 'uses' => 'HomeController@getIndex',]);
