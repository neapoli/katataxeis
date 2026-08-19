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
   *
   * I valori arrivano sempre dai template Twig dei campi calcolati, che usano
   * la convenzione inglese: punto decimale e nessun raggruppamento ("1234.5"),
   * oppure virgola per le migliaia dove il template usa number_format()
   * ("1,234.50"). Viene riconosciuto anche il formato italiano ("1.234,50"),
   * identificabile dalla presenza di entrambi i separatori.
   */
  private function toFloat(mixed $value): float {
    if (is_null($value)) {
      return 0.0;
    }

    // Gestisce oggetti Markup, oggetti con __toString, stringhe e numeri.
    $string = trim(strip_tags((string) $value));
    if ($string === '') {
      return 0.0;
    }

    // Il segno va letto prima di ripulire la stringa: dopo non è più
    // distinguibile da un trattino qualsiasi.
    $is_negative = str_starts_with($string, '-');

    // Tiene solo cifre e separatori, scartando spazi, valute e unità di misura
    // eventualmente presenti in un valore formattato.
    $string = preg_replace('/[^0-9.,]/', '', $string);
    if ($string === '') {
      return 0.0;
    }

    // Ricompone il numero attorno al separatore decimale: tutti gli altri
    // separatori raggruppano le migliaia e vanno scartati.
    $decimal_position = $this->findDecimalPosition($string);
    if ($decimal_position === NULL) {
      $string = preg_replace('/\D/', '', $string);
    }
    else {
      $integer_part = preg_replace('/\D/', '', substr($string, 0, $decimal_position));
      $decimal_part = preg_replace('/\D/', '', substr($string, $decimal_position + 1));
      $string = ($integer_part === '' ? '0' : $integer_part)
        . '.' . ($decimal_part === '' ? '0' : $decimal_part);
    }

    if (!is_numeric($string)) {
      return 0.0;
    }

    return $is_negative ? -(float) $string : (float) $string;
  }

  /**
   * Individua il separatore decimale in un numero già ripulito.
   *
   * @param string $string
   *   Una stringa composta solo da cifre, punti e virgole.
   *
   * @return int|null
   *   La posizione del separatore decimale, NULL se il numero è intero.
   */
  private function findDecimalPosition(string $string): ?int {
    $last_dot = strrpos($string, '.');
    $last_comma = strrpos($string, ',');

    // Con entrambi i separatori l'ultimo è per forza il decimale e l'altro le
    // migliaia: vale sia per "1,234.50" sia per "1.234,50".
    if ($last_dot !== FALSE && $last_comma !== FALSE) {
      return max($last_dot, $last_comma);
    }

    // Una virgola che chiude un gruppo di esattamente tre cifre è il
    // raggruppamento delle migliaia di number_format(): "1,234" vale 1234.
    // Negli altri casi è un decimale scritto all'italiana: "12,5" vale 12,5.
    if ($last_comma !== FALSE) {
      return preg_match('/,\d{3}$/', $string) ? NULL : $last_comma;
    }

    // Il punto è sempre decimale, perché è così che Twig e PHP stampano i
    // float, anche sopra il migliaio: 1234,5 viene scritto "1234.5", mai
    // "1.234,5". Quindi "1.234" vale 1,234 e non milleduecentotrentaquattro.
    return $last_dot === FALSE ? NULL : $last_dot;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {
    // getData() restituisce i valori inseriti insieme ai campi calcolati,
    // ricalcolati al momento: e' da li' che arrivano i totali di sezione.
    $data = $webform_submission->getData();

    $tot_anzianita = $this->toFloat($data['totale_anzianita_servizio'] ?? NULL);
    $tot_esigenze = $this->toFloat($data['totale_esigenze_famiglia'] ?? NULL);
    $tot_titoli = $this->toFloat($data['totale_titoli_generali'] ?? NULL);

    $totale = $tot_anzianita + $tot_esigenze + $tot_titoli;

    // Scrive la sola chiave calcolata qui. setData() sostituirebbe in blocco i
    // dati grezzi con l'array restituito da getData(), cementando i valori dei
    // campi calcolati come se fossero stati inseriti dall'utente.
    $webform_submission->setElementData('totale_punteggio', $totale);
  }

}
