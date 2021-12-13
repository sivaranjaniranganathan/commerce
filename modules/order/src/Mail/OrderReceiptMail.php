<?php

namespace Drupal\commerce_order\Mail;

use Drupal\commerce\MailHandlerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderTotalSummaryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class OrderReceiptMail implements OrderReceiptMailInterface {

  use StringTranslationTrait;

  /**
   * The mail handler.
   *
   * @var \Drupal\commerce\MailHandlerInterface
   */
  protected $mailHandler;

  /**
   * The order total summary.
   *
   * @var \Drupal\commerce_order\OrderTotalSummaryInterface
   */
  protected $orderTotalSummary;

  /**
   * The profile view builder.
   *
   * @var \Drupal\profile\ProfileViewBuilder
   */
  protected $profileViewBuilder;

  /**
   * Constructs a new OrderReceiptMail object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce\MailHandlerInterface $mail_handler
   *   The mail handler.
   * @param \Drupal\commerce_order\OrderTotalSummaryInterface $order_total_summary
   *   The order total summary.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MailHandlerInterface $mail_handler, OrderTotalSummaryInterface $order_total_summary) {
    $this->mailHandler = $mail_handler;
    $this->orderTotalSummary = $order_total_summary;
    $this->profileViewBuilder = $entity_type_manager->getViewBuilder('profile');
  }

  /**
   * {@inheritdoc}
   */
  public function send(OrderInterface $order, $to = NULL, $bcc = NULL) {
    $to = isset($to) ? $to : $order->getEmail();
    if (!$to) {
      // The email should not be empty.
      return FALSE;
    }

    $sid = $order->field_submission_id->getValue()[0]['value'];
    $submission = \Drupal\webform\Entity\WebformSubmission::load($sid);
    $submission_data = $submission->getData();
    $first_name = $submission_data['field_first_name'];
    $last_name = $submission_data['field_last_name'];
    $customer_name = $first_name." ".$last_name;

    $customer_name = isset($customer_name) ? $customer_name : $order->getEmail();
    $subject = $this->t('Registration Confirmed : @customer_name', ['@customer_name' => $customer_name]);

    $body = [
      '#theme' => 'commerce_order_receipt',
      '#order_entity' => $order,
      '#totals' => $this->orderTotalSummary->buildTotals($order),
    ];
    if ($billing_profile = $order->getBillingProfile()) {
      $body['#billing_information'] = $this->profileViewBuilder->view($billing_profile);
    }

    $bcc = $to;
    
    $params = [
      'id' => 'order_receipt',
      'from' => $order->getStore()->getEmail(),
      'bcc' => $bcc,
      'order' => $order,
    ];
    $customer = $order->getCustomer();
    if ($customer->isAuthenticated()) {
      $params['langcode'] = $customer->getPreferredLangcode();
    }

    $to = "registration@thesnellgroup.com";
    return $this->mailHandler->sendMail($to, $subject, $body, $params);
  }

}
