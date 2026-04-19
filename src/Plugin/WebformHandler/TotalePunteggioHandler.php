<?php

namespace Drupal\mio_modulo\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Calcola il totale punteggio e lo salva nel campo numerico.
 *
 * @WebformHandler(
 *   id = "totale_punteggio_handler",
 *   label = @Translation("Calcolo Totale Punteggio"),
 *   category = @Translation("Custom"),
 *   description = @Translation("Calcola la somma dei totali e la salva in totale_punteggio."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class TotalePunteggioHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {
    $data = $webform_submission->getData();

    $tot_anzianita = (float) ($data['totale_anzianita_servizio'] ?? 0);
    $tot_esigenze  = (float) ($data['totale_esigenze_famiglia'] ?? 0);
    $tot_titoli    = (float) ($data['totale_titoli_generali'] ?? 0);

    $totale = $tot_anzianita + $tot_esigenze + $tot_titoli;

    $data['totale_punteggio'] = $totale;
    $webform_submission->setData($data);
  }

}
