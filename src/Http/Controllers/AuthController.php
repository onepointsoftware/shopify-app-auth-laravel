<?php
namespace OnePointSoftware\ShopifyAuth\Http\Controllers;

use OnePointSoftware\ShopifyAuth\Services\ShopifyAuthService;
use OnePointSoftware\ShopifyAuth\ShopifyApi;
use OnePointSoftware\ShopifyAuth\Models\ShopifyUser;
use OnePointSoftware\ShopifyAuth\Models\ShopifyAppUsers;
use OnePointSoftware\ShopifyAuth\Models\ShopifyWebhooks;
use OnePointSoftware\ShopifyAuth\Models\ShopifyScriptTag;
use Illuminate\Http\Request;

class AuthController
{
    protected $shopify;
    protected $shopifyAuthService;
    protected $shopifyAppConfig;

    public function __construct(ShopifyApi $shopify, ShopifyAuthService $shopifyAuthService)
    {
        $this->shopify = $shopify;
        $this->shopifyAuthService = $shopifyAuthService;
    }

    public function installShop(Request $request, $appName)
    {
        $shopifyAppConfig = config('shopify-auth.'.$appName);
        $shopUrl = $request->get('shop');

        if (!$shopUrl) {
            abort(401, 'No shop url set, cannot authorize.');
        }

        $scope = $shopifyAppConfig['scope'];
        $redirectUrl = url($shopifyAppConfig['redirect_url']);
        $appName = $shopifyAppConfig['name'];

        $user = ShopifyUser::where('shop_url', $shopUrl)->whereHas('shopifyAppUsers', function($query) use ($appName) {
            $query->whereShopifyAppName($appName);
        })->get();

        // if existing user for this app, send to dashboard
        if ($user !== null && !$user->isEmpty() && $user->shopifyAppUsers->count()) {
            return redirect()->to($shopifyAppConfig['dashboard_url']);
        }

        $shopify = $this->shopify
            ->setKey($shopifyAppConfig['key'])
            ->setSecret($shopifyAppConfig['secret'])
            ->setShopUrl($shopUrl);

        return redirect()->to($shopify->getAuthorizeUrl($scope, $redirectUrl));
    }

    public function processOAuthResultRedirect(Request $request, $appName)
    {
        $shopifyAppConfig = config('shopify-auth.'.$appName);
        $code = $request->get('code');
        $shopUrl = $request->get('shop');

        // Save into DB
        $createUser = $this->shopifyAuthService->getAccessTokenAndCreateNewUser($code, $shopUrl, $shopifyAppConfig);

        // Create webhook to handle uninstallation
        $this->shopifyAuthService->checkAndAddWebhookForUninstall($shopUrl, $createUser['access_token'], $createUser['user'], $shopifyAppConfig);

        // Build query string
        $queryString = [
            'shop' => $shopUrl,
            'appName' => $appName,
        ];
        $queryString = http_build_query($queryString) . "\n";

        return redirect()->to($shopifyAppConfig['success_url'] . '?' . $queryString)->with('shopUrl', $shopUrl);
    }

    public function getSuccessPage($appName)
    {
        $shopifyAppConfig = config('shopify-auth.'.$appName);

        return view($shopifyAppConfig['view_install_success_path']);
    }

    public function handleAppUninstallation(Request $request, $appName)
    {
        $shopUrl = $request->get('domain');

        \Log::info('handle uninstall webhook');

        $user = ShopifyUser::where('shop_url', $shopUrl)->get()->first();

        \Log::debug('Uninstall log', [
            'user' => $user,
            'request' => $request->all(),
            'app_name' => $appName,
        ]);

        // remove app users
        $userApps = ShopifyAppUsers::where([
            'shopify_app_name' => $appName,
            'shopify_users_id' => $user->id,
        ])->get();

        foreach ($userApps as $app) {
            $app->delete();
        }

        // remove script tags
        $tags = ShopifyScriptTag::where([
            'shop_url' => $shopUrl,
            'shopify_app' => $appName,
        ])->get();
        
        foreach ($tags as $tag) {
            $tag->delete();
        }

    }
}
