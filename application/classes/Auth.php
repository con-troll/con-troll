<?php

class Auth {
	
	const USER_AUTH_PROVIDER = 'user-auth-provider';
	
	static private $_last_provider = null;
	
	/**
	 * Initialize and cache a provider implementation for the specified provider name
	 * @return Auth_ProviderIf
	 * @throws Exception
	 */
	public static function getProvider($provider, $callback_url) {
		$config = static::getConfig();
		if (!$config[$provider])
			throw new Exception("Invalid provider specified: #{$provider}");
		$prov_config = $config[$provider];
		$fullclass = "Auth_" . str_replace('/', '_', $prov_config['type']);
		$impl = new $fullclass($provider, $prov_config, $callback_url);
		Session::instance()->bind(self::USER_AUTH_PROVIDER, $impl);
		return $impl;
	}
	
	/**
	 * Find a cached provider implementation
	 * @return Auth_ProviderIf
	 * @throws Exception
	 */
	public static function getLastProvider() {
		static::$_last_provider = static::$_last_provider ?: Session::instance()->get(self::USER_AUTH_PROVIDER);
		if (is_null(static::$_last_provider))
			throw new Exception("Failed to find current auth provider");
		return static::$_last_provider;
	}
	
	/**
	 * Generate a list of configured providers to allow the user to select a provider they want to use
	 * @return array list of provider identifiers
	 */
	public static function listProviders() {
		return array_keys(static::getConfig()->getArrayCopy());
	}
	
	public static function getLoginButton($provider) {
		return static::getConfig()[$provider]['button'];
	}
	
	public static function getProviderType($provider) {
		return static::getConfig()[$provider]['type'];
	}
	
	public static function getProviderName($provider) {
		return static::getConfig()[$provider]['name'];
	}
	
	private static function getConfig() {
		return Kohana::$config->load('auth');
	}
}
