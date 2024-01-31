<?php

namespace Drupal\mips\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;

/**
 *  Provides the interface for the MIPS payment gateway.
 */
interface MipsPaymentGatewayInterface extends OffsitePaymentGatewayInterface {

  /**
   * Returns an iframe embedding the MIPS Checkout page.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return string
   *   The iframe HTML to use to embed MIPS Hosted Checkout page on-site.
   */
  public function getMipsCheckoutIframe(OrderInterface $order, string $return_url): string;

}
