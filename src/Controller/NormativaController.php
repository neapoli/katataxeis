<?php

namespace Drupal\katataxeis\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * La normativa di riferimento delle graduatorie interne.
 *
 * Le schede e i formulari rimandano alle note e agli articoli del CCNI sulla
 * mobilità: chi compila e chi controlla deve poterli leggere dal modulo, nel
 * testo ufficiale, senza cercarli su internet, dove circolano anche versioni
 * superate. Il PDF è quello pubblicato dal Ministero e sta dentro il modulo.
 */
class NormativaController extends ControllerBase {

  /**
   * Il file del CCNI, relativo alla cartella del modulo.
   */
  const PDF = 'documenti/ccni-mobilita-2025-2028.pdf';

  /**
   * Le parti del CCNI a cui rimandano schede e formulari, con la pagina del PDF.
   *
   * Le pagine sono quelle del file, non i numeri stampati in fondo al foglio.
   */
  const PARTI = [
    'Personale docente' => [
      ['Art. 13 - Sistema delle precedenze ed esclusione dalla graduatoria interna d\'istituto', 22],
      ['Art. 17 - Contenzioso (reclami)', 30],
      ['Art. 19 - Individuazione perdenti posto della scuola dell\'infanzia e della scuola primaria', 32],
      ['Art. 21 - Individuazione perdenti posto nella scuola secondaria di I e II grado', 37],
      ['Allegato 2, Tabella A - Valutazione dei titoli ai fini dei trasferimenti a domanda e d\'ufficio', 87],
      ['Note comuni alle tabelle dei trasferimenti', 92],
    ],
    'Personale ATA' => [
      ['Art. 40 - Sistema delle precedenze ed esclusione dalla graduatoria interna d\'istituto', 58],
      ['Art. 42 - Contenzioso (reclami)', 66],
      ['Art. 45 - Individuazione del restante personale soprannumerario e dimensionamento', 69],
      ['Allegato E - Tabella di valutazione dei titoli e dei servizi: I - Anzianità di servizio', 99],
      ['Allegato E - II - Esigenze di famiglia', 101],
      ['Allegato E - III - Titoli generali e note (1)-(11)', 102],
    ],
  ];

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Constructs a new NormativaController.
   */
  public function __construct(ModuleExtensionList $module_extension_list) {
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('extension.list.module'));
  }

  /**
   * La pagina con il documento e i collegamenti alle parti che servono.
   *
   * @return array
   *   Il contenuto della pagina.
   */
  public function pagina() {
    $pdf = Url::fromRoute('katataxeis.normativa_pdf')->toString();
    $nuova_scheda = ['target' => '_blank', 'rel' => 'noopener'];

    $build['introduzione'] = [
      '#markup' => '<p>' . $this->t('Le schede della graduatoria interna rimandano alle note e agli articoli del <strong>Contratto collettivo nazionale integrativo sulla mobilità</strong> del personale docente, educativo e ATA per gli anni scolastici 2025/26, 2026/27 e 2027/28: <strong>testo definitivo sottoscritto il 10 marzo 2026</strong>, come pubblicato dal Ministero dell\'istruzione e del merito.') . '</p>',
    ];

    $build['documento'] = [
      '#type' => 'link',
      '#title' => $this->t('Apri il CCNI completo (PDF, 112 pagine)'),
      '#url' => Url::fromRoute('katataxeis.normativa_pdf'),
      '#attributes' => ['class' => ['button', 'button--primary']] + $nuova_scheda,
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    foreach (self::PARTI as $gruppo => $parti) {
      $voci = [];
      foreach ($parti as [$titolo, $pagina]) {
        $voci[] = [
          '#type' => 'html_tag',
          '#tag' => 'a',
          '#value' => $this->t('@titolo (pag. @pagina)', ['@titolo' => $titolo, '@pagina' => $pagina]),
          '#attributes' => ['href' => $pdf . '#page=' . $pagina] + $nuova_scheda,
        ];
      }
      $build[$gruppo] = [
        '#theme' => 'item_list',
        '#title' => $gruppo,
        '#items' => $voci,
      ];
    }

    $build['nota'] = [
      '#markup' => '<p><small>' . $this->t('I collegamenti aprono il documento alla pagina indicata. Se il browser apre il PDF dall\'inizio, la pagina indicata è quella del file.') . '</small></p>',
    ];

    return $build;
  }

  /**
   * Il PDF del CCNI, da leggere nel browser.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Il documento.
   */
  public function pdf() {
    $file = DRUPAL_ROOT . '/' . $this->moduleExtensionList->getPath('katataxeis') . '/' . self::PDF;
    if (!is_file($file)) {
      throw new NotFoundHttpException();
    }

    $risposta = new BinaryFileResponse($file, 200, ['Content-Type' => 'application/pdf']);
    // «inline» perché si apra nel browser, dove i collegamenti #page= portano
    // alla pagina giusta; il nome resta quello giusto se lo si salva.
    $risposta->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'ccni-mobilita-2025-2028.pdf');

    return $risposta;
  }

}
