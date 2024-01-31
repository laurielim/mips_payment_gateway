<?php

namespace Drupal\mips\PluginForm;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;

/**
 * Provides an iFrame payment form for MIPS Payment Gateway.
 */
class MipsPaymentForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $configuration = $this->plugin->getConfiguration();

    // Return an error if the gateway's settings haven't been configured.
    $config_keys = ['id_merchant', 'id_entity', 'id_operator', 'operator_password', 'basic_username', 'basic_password'];
    foreach ($config_keys as $key) {
      if (empty($configuration[$key])) {
        throw new PaymentGatewayException($key . 'is missing from MIPS payment gateway configurations.');
      }
    }

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order = $payment->getOrder();
    $iframe = $this->plugin->getMipsCheckoutIframe($order, $form['#return_url']);
    $form['iframe'] = [
      '#markup' => Markup::create($iframe),
    ];
    $cancel_link = Link::createFromRoute($this->t('Cancel payment and go back'), 'commerce_payment.checkout.cancel', [
      'commerce_order' => $order->id(),
      'step' => 'payment',
    ])->toString();
    $form['iframe']['#prefix'] = '<div class="commerce-mips-cancel">' . $form['#return_url'] . '</div>';
    $form['iframe']['#suffix'] = '<div class="commerce-mips-cancel">' . $cancel_link . '</div>';

    return $form;
  }

}
