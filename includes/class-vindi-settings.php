<?php

class Vindi_Settings extends WC_Settings_API
{
    /**
     * @var Vindi_WooCommerce_Subscriptions
     **/
    private $plugin;

    /**
     * @var string
     **/
    private $token;

    /**
     * @var boolean
     **/
    private $debug;

    /**
     * @var Vindi_API
     **/
    public $api;

    /**
     * @var Vindi_Logger
     **/
    public $logger;

    /**
     * @var WooCommerce
     **/
    public $woocommerce;


    public function __construct()
    {
        global $woocommerce;

        $this->token = sanitize_file_name(wp_hash(VINDI_IDENTIFIER));

        $this->init_form_fields();
        $this->init_settings();

        $this->debug       = $this->get_option('debug') == 'yes' ? true : false;
        $this->logger      = new Vindi_Logger(VINDI_IDENTIFIER, $this->debug);
        $this->api         = new Vindi_API($this->get_api_key(), $this->logger);
        $this->woocommerce = $woocommerce;

        add_filter('woocommerce_settings_tabs_array', array(&$this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_settings_vindi', array(&$this, 'settings_tab'));
        add_action('woocommerce_update_options_settings_vindi', array(&$this, 'process_admin_options'));
        add_filter('woocommerce_payment_gateways', array(&$this, 'add_gateway'));
    }

    /**
     * Create settings tab
     */
    public static function add_settings_tab($settings_tabs)
    {
        $settings_tabs['settings_vindi'] = __('Vindi', VINDI_IDENTIFIER);
        return $settings_tabs;
    }

    /**
     * Include Settings View
     */
    public function settings_tab()
    {
        $this->get_template('admin-settings.html.php', array('settings' => $this));
    }

    /**
	 * Initialize Gateway Settings Form Fields
	 * @return void
	 */
	public function init_form_fields()
    {
		$url           = admin_url(sprintf('admin.php?page=wc-status&tab=logs&log_file=%s-%s-log', VINDI_IDENTIFIER, $this->get_token()));
		$logs_url      = '<a href="' . $url . '" target="_blank">' . __('Ver Logs', VINDI_IDENTIFIER) . '</a>';
		$nfe_know_more = '<a href="http://atendimento.vindi.com.br/hc/pt-br/articles/204450944-Notas-fiscais" target="_blank">' . __('Saiba mais', VINDI_IDENTIFIER) . '</a>';

		$prospects_url = '<a href="https://app.vindi.com.br/prospects/new" target="_blank">' . __('Não possui uma conta?', VINDI_IDENTIFIER) . '</a>';

		$this->form_fields = array(
			'api_key'              => array(
				'title'            => __('Chave da API Vindi', VINDI_IDENTIFIER),
				'type'             => 'text',
				'description'      => __('A Chave da API de sua conta na Vindi. ' . $prospects_url, VINDI_IDENTIFIER),
				'default'          => '',
            ),
			'send_nfe_information' => array(
				'title'            => __('Emissão de NFe\'s', VINDI_IDENTIFIER),
				'label'            => __('Enviar informações para emissão de NFe\'s', VINDI_IDENTIFIER),
				'type'             => 'checkbox',
				'description'      => sprintf(__('Envia informações de RG e Inscrição Estadual para Emissão de NFe\'s com nossos parceiros. %s', VINDI_IDENTIFIER), $nfe_know_more),
				'default'          => 'no',
			),
			'return_status'        => array(
				'title'            => __('Status de conclusão do pedido', VINDI_IDENTIFIER),
				'type'             => 'select',
				'description'      => __('Status que o pedido deverá ter após receber a confirmação de pagamento da Vindi.', VINDI_IDENTIFIER),
				'default'          => 'processing',
				'options'          => array(
					'processing'   => 'Processando',
					'on-hold'      => 'Aguardando',
					'completed'    => 'Concluído',
				),
			),
			'testing'              => array(
				'title'            => __('Testes', 'vindi-woocommerce'),
				'type'             => 'title',
			),
			'debug'                => array(
				'title'            => __('Log de Depuração', VINDI_IDENTIFIER),
				'label'            => __('Ativar Logs', VINDI_IDENTIFIER),
				'type'             => 'checkbox',
				'description'      => sprintf(__('Ative esta opção para habilitar logs de depuração do servidor. %s', VINDI_IDENTIFIER), $logs_url),
				'default'          => 'no',
			),
		);
	}

    /**
     * Get Uniq Token Access
     *
     * @return string
     **/
    public function get_token()
    {
        return $this->token;
    }

    /**
     * Get Vindi API Key
     * @return string
     **/
    public function get_api_key()
    {
        return $this->settings['api_key'];
    }

    /**
     * Get Vindi API Key
     * @return string
     **/
    public function send_nfe_information()
    {
        return 'yes' === $this->settings['send_nfe_information'];
    }

    /**
	 * Return the URL that will receive the webhooks.
	 * @return string
	 */
	public function get_events_url() {
		return sprintf('%s/wc-api/?action=vindi&token=%s', get_site_url(), $this->get_token());
	}

    /**
     * Check if SSL is enabled when merchant is not trial.
     * @return boolean
     */
    public function check_ssl()
    {
        return $this->api->is_merchant_status_trial() || $this->check_woocommerce_force_ssl();
    }

    /**
     * @return boolean
     **/
    public function check_woocommerce_force_ssl_checkout()
    {
        return 'yes' === get_option('woocommerce_force_ssl_checkout') && is_ssl();
    }

    /**
     * Add the gateway to WooCommerce.
     *
     * @param   array $methods WooCommerce payment methods.
     *
     * @return  array Payment methods with Vindi.
     */
    public function add_gateway($methods)
    {
    	$methods[] = new Vindi_BankSlip_Gateway($this);

    	return $methods;
    }

    /**
     * WC Get Template helper.
     *
     * @param       $name
     * @param array $args
     */
    public function get_template($name, $args = array())
    {
        wc_get_template($name, $args, '', Vindi_WooCommerce_Subscriptions::VIEWS_DIR);
    }
}