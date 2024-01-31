<?php

namespace Drupal\mips\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the MIPS off-site (iFrame) payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "mips_payment_gateway",
 *   label = @Translation("MIPS Payment Gateway"),
 *   display_label = @Translation("Online Payment"),
 *   modes = {
 *     "test" = @Translation("Staging"),
 *     "live" = @Translation("Production"),
 *   },
 *   credit_card_types = {
 *     "mastercard", "visa",
 *   },
 *   forms = {
 *     "offsite-payment" = "Drupal\mips\PluginForm\MipsPaymentForm",
 *   },
 * )
 */
class MipsPaymentGateway extends OffsitePaymentGatewayBase implements MipsPaymentGatewayInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $httpClient;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ContainerFactoryPluginInterface {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.channel.commerce_payment');
    $instance->httpClient = $container->get('http_client');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration(): array {
    return [
      'id_merchant' => '',
      'id_entity' => '',
      'id_operator' => '',
      'operator_password' => '',
      'basic_username' => '',
      'basic_password' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['service_description'] = [
      '#markup' => $this->t('Contact MIPS to get your credentials at <a href="mailto:support@mips.mu">support@mips.mu</a>'),
      '#weight' => '-100',
    ];

    $form['id_merchant'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant Id'),
      '#default_value' => $this->configuration['id_merchant'],
      '#required' => TRUE,
    ];

    $form['id_entity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity Id'),
      '#default_value' => $this->configuration['id_entity'],
      '#required' => TRUE,
    ];

    $form['id_operator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Operator Id'),
      '#default_value' => $this->configuration['id_operator'],
      '#required' => TRUE,
    ];

    $form['operator_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Operator Password'),
      '#default_value' => $this->configuration['operator_password'],
      '#required' => TRUE,
    ];

    $form['basic_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Basic Username'),
      '#default_value' => $this->configuration['basic_username'],
      '#required' => TRUE,
    ];

    $form['basic_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Basic Password'),
      '#default_value' => $this->configuration['basic_password'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    $this->configuration['id_merchant'] = $values['id_merchant'];
    $this->configuration['id_entity'] = $values['id_entity'];
    $this->configuration['id_operator'] = $values['id_operator'];
    $this->configuration['operator_password'] = $values['operator_password'];
    $this->configuration['basic_username'] = $values['basic_username'];
    $this->configuration['basic_password'] = $values['basic_password'];
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    // @todo Create Payment entity.
    // See https://docs.drupalcommerce.org/commerce2/developer-guide/payments/create-payment-gateway/off-site-gateways/return-from-payment-provider.
  }

  /**
   * {@inheritDoc}
   */
  public function getMipsCheckoutIframe(OrderInterface $order, string $return_url): string {
    $configuration = $this->getConfiguration();
    $total_price = $order->getTotalPrice();

    $data = [
      'authentify' => [
        'id_merchant' => $configuration['id_merchant'],
        'id_entity' => $configuration['id_entity'],
        'id_operator' => $configuration['id_operator'],
        'operator_password' => $configuration['operator_password'],
      ],
      "order" => [
        "id_order" => $order->id(),
        "currency" => $total_price->getCurrencyCode(),
        "amount" => $total_price->getNumber(),
      ],
      "iframe_behavior" => [
        "height" => '700px',
        "width" => '100%',
        "custom_redirection_url" => $return_url,
        "language" => $this->languageManager->getCurrentLanguage()->getId(),
      ],
      "request_mode" => "simple",
      "touchpoint" => "web",
    ];

    $auth_header = 'Basic ' . base64_encode($configuration['basic_username'] . ':' . $configuration['basic_password']);

    return $this->apiRequest($data, $auth_header);
  }

  /**
   * Submits an API request to MIPS.
   *
   * @param array $data
   *   Information required to create payment iframe.
   *
   * @param array $auth_header
   *   Authorization header.
   *
   * @return string
   *   The response content from MIPS if successful or FALSE on error.
   */
  protected function apiRequest(array $data, string $auth_header): string {
    $url = $this->getServerUrl();
    $body = json_encode($data);
    try {
      $response = $this->httpClient->post($url, [
        'headers' => [
          'Authorization' => $auth_header,
        ],
        'body' => $body,
        'timeout' => 45,
      ]);
    }
    catch (GuzzleException $exception) {
      $this->logger->error($exception->getResponse()->getBody()->getContents());
      throw new PaymentGatewayException('Connection to MIPS failed. Please try again or contact an administrator to resolve the issue.');
    }

    return $response->getBody()->getContents();
  }

  /**
   * Returns the URL to a MIPS API server.
   *
   * @return string
   *   The request URL with a trailing slash.
   */
  private function getServerUrl() {
    switch ($this->getConfiguration()['mode']) {
      case 'test':
        return 'test-url'; // may be fetched from env variables

      case 'live':
        return 'production-url'; // may be fetched from env variables
    }

    return '';
  }

}
