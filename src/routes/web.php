<?php

// Install & Auth
Route::group(['namespace' => 'OnePointSoftware\ShopifyAuth\Http\Controllers'], function () {
    Route::get('shopify-auth/{appName}/install', 'AuthController@installShop');
    Route::get('shopify-auth/{appName}/auth/callback', 'AuthController@processOAuthResultRedirect');
});

Route::group(['namespace' => 'OnePointSoftware\ShopifyAuth\Http\Controllers', 'middleware' => ['web', 'OnePointSoftware\ShopifyAuth\Http\Middleware\ShopifyAuthCheck']], function () {
    Route::get('shopify-auth/{appName}/install/success', 'AuthController@getSuccessPage');
});

// Webhooks
Route::group(['namespace' => 'OnePointSoftware\ShopifyAuth\Http\Controllers'], function () {
	Route::get('webhooks/{appName}/uninstalled', 'AuthController@handleAppUninstallation');
	Route::post('webhooks/{appName}/uninstalled', 'AuthController@handleAppUninstallation');
});
