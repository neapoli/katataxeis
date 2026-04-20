<?php

namespace Drupal\katataxeis\Plugin\WebformHandler;

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
   * Converte un valore di campo webform in float in modo robusto.
   */
  private function toFloat(mixed $value): float {
    if (is_null($value)) {
      return 0.0;
    }
    // Gestisce oggetti Markup, oggetti con __toString, stringhe e numeri
    $string = strip_tags((string) $value);
    // Rimuove spazi, sostituisce virgola con punto (formato italiano)
    $string = trim(str_replace(',', '.', $string));
    // Rimuove qualsiasi carattere non numerico eccetto punto e segno meno
    $string = preg_replace('/[^0-9.\-]/', '', $string);
    return is_numeric($string) ? (float) $string : 0.0;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {
    $data = $webform_submission->getData();

    $tot_anzianita = $this->toFloat($data['totale_anzianita_servizio'] ?? NULL);
    $tot_esigenze  = $this->toFloat($data['totale_esigenze_famiglia'] ?? NULL);
    $tot_titoli    = $this->toFloat($data['totale_titoli_generali'] ?? NULL);

    $totale = $tot_anzianita + $tot_esigenze + $tot_titoli;

    $data['totale_punteggio'] = $totale;
    $webform_submission->setData($data);
  }

}
