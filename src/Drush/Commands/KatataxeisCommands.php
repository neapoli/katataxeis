<?php

namespace Drupal\katataxeis\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Commands\config\ConfigImportCommands;
use Drush\Commands\core\UpdateDBCommands;
use Drush\Drush;

/**
 * Comandi Drush del modulo Katatáxeis.
 */
class KatataxeisCommands extends DrushCommands {

  /**
   * Evita di ripetere l'allineamento se il comando viene annidato.
   *
   * @var bool
   */
  protected $applied = FALSE;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Costruttore.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(ModuleExtensionList $module_extension_list, Connection $database) {
    parent::__construct();
    $this->moduleExtensionList = $module_extension_list;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('extension.list.module'),
      $container->get('database')
    );
  }

  /**
   * Riapplica config/update dopo ogni "drush updb".
   *
   * Per il modulo principale e per ogni sottomodulo attivo esegue lo stesso
   * comando che si lancerebbe a mano:
   * @code
   * drush -y config:import --partial --source=.../katataxeis/config/update
   * @endcode
   * A differenza di hook_post_update_NAME(), che core esegue una sola volta per
   * sito, questo hook parte a ogni esecuzione di updatedb, anche quando non ci
   * sono aggiornamenti in sospeso.
   *
   * @param mixed $result
   *   Il risultato del comando updatedb.
   * @param \Consolidation\AnnotatedCommand\CommandData $command_data
   *   I dati del comando in esecuzione.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: UpdateDBCommands::UPDATEDB)]
  public function reapplyKatataxeisConfig($result, CommandData $command_data): void {
    if ($this->applied) {
      return;
    }
    $this->applied = TRUE;

    $this->importConfigUpdate();
  }

  /**
   * Riallinea le configurazioni di config/update.
   *
   * Quelle del modulo principale e quelle dei sottomoduli attivi
   * (katataxeis_comprensivo, katataxeis_secondo_grado): un istituto
   * comprensivo non riceve il formulario del II grado, e viceversa.
   * Utile per riapplicarle senza attendere il prossimo "drush updb".
   */
  #[CLI\Command(name: 'katataxeis:config-update', aliases: ['katataxeis-cu'])]
  #[CLI\Usage(name: 'drush katataxeis:config-update', description: 'Riapplica le configurazioni di config/update del modulo e dei sottomoduli attivi.')]
  public function importConfigUpdate(): void {
    foreach (katataxeis_moduli_attivi() as $modulo) {
      $this->importaCartella($modulo, katataxeis_cartella_config_update($modulo));
    }
  }

  /**
   * Importa la cartella config/update di un modulo.
   *
   * @param string $modulo
   *   Il modulo, per i messaggi.
   * @param string $source
   *   La cartella.
   */
  protected function importaCartella($modulo, $source): void {
    // Senza file da importare config:import uscirebbe in errore.
    if (!is_dir($source) || !glob($source . '/*.yml')) {
      $this->logger()->notice(dt('@modulo: nessuna configurazione da riapplicare (config/update vuota).', ['@modulo' => $modulo]));
      return;
    }

    $this->logger()->notice(dt('@modulo: riapplico le configurazioni da @source', ['@modulo' => $modulo, '@source' => $source]));

    $process = $this->processManager()->drush(
      Drush::aliasManager()->getSelf(),
      ConfigImportCommands::IMPORT,
      [],
      ['partial' => TRUE, 'source' => $source, 'yes' => TRUE]
    );

    // Volutamente run() e non mustRun(): config:import valida le dipendenze e
    // fallisce, per esempio, se una configurazione cita un modulo non
    // installato su questa scuola. Con mustRun() l'errore interromperebbe
    // l'intero "drush updb"; così l'aggiornamento arriva in fondo e il
    // problema resta ben visibile nel log.
    $process->run();
    $this->output()->writeln($process->getOutput());

    if (!$process->isSuccessful()) {
      $this->logger()->error(dt("@modulo: l'allineamento di config/update è fallito. @error", [
        '@modulo' => $modulo,
        '@error' => $process->getErrorOutput(),
      ]));
      return;
    }

    $this->logger()->success(dt('@modulo: configurazioni di config/update riapplicate.', ['@modulo' => $modulo]));
  }

  /**
   * Porta l'anno scolastico delle schede già inviate all'anno della mobilità.
   *
   * Fino al 2026 le scuole indicavano l'anno in corso al momento della
   * compilazione: a marzo 2026, «2025/2026», per la mobilità 2026/27. Il
   * formulario ora propone l'anno della mobilità, come lo intende il CCNI
   * (katataxeis_anno_scolastico_graduatoria()). Il comando sposta di un anno le
   * schede con l'etichetta vecchia, perché la serie resti continua e il
   * pre-ruolo (voci B1) si legga con l'anno giusto.
   *
   * Si sposta solo una scheda che porta proprio l'anno in corso alla data
   * d'invio: quelle già giuste, o con un anno scelto diversamente, restano
   * come sono. Per questo il comando si può lanciare più volte.
   *
   * Cambia solo l'etichetta: i punti restano quelli salvati, perché quelle
   * graduatorie sono già state usate e l'anno dopo si compila una scheda nuova.
   * Senza --applica mostra soltanto cosa farebbe.
   */
  #[CLI\Command(name: 'katataxeis:sposta-anno', aliases: ['katataxeis-sa'])]
  #[CLI\Option(name: 'applica', description: 'Applica lo spostamento; senza, mostra soltanto l\'elenco.')]
  #[CLI\Usage(name: 'drush katataxeis:sposta-anno', description: 'Mostra quali schede verrebbero spostate, senza modificare nulla.')]
  #[CLI\Usage(name: 'drush katataxeis:sposta-anno --applica', description: 'Sposta le schede elencate.')]
  public function spostaAnno(array $options = ['applica' => FALSE]): void {
    $schede = $this->database->select('webform_submission', 's');
    $schede->join('webform_submission_data', 'd', "d.sid = s.sid AND d.name = 'anno_scolastico'");
    $schede->leftJoin('webform_submission_data', 'c', "c.sid = s.sid AND c.name = 'cognome'");
    $schede->leftJoin('webform_submission_data', 'n', "n.sid = s.sid AND n.name = 'nome'");
    $schede->fields('s', ['sid', 'webform_id', 'uid', 'created', 'completed'])
      ->addField('d', 'value', 'anno');
    $schede->addField('c', 'value', 'cognome');
    $schede->addField('n', 'value', 'nome');
    $schede->condition('s.webform_id', KATATAXEIS_ANNO_AUTOMATICO, 'IN')
      ->condition('s.in_draft', 0)
      // Le più recenti per prime: liberano l'anno che servirà alle più vecchie.
      ->orderBy('d.value', 'DESC')
      ->orderBy('s.sid');

    // Gli anni già occupati, per formulario e persona.
    $occupati = [];
    $righe = [];
    foreach ($schede->execute() as $scheda) {
      $occupati[$scheda->webform_id][$scheda->uid][$scheda->anno] = TRUE;
      $righe[] = $scheda;
    }

    $da_spostare = [];
    $saltate = [];
    foreach ($righe as $scheda) {
      $inviata = $scheda->completed ?: $scheda->created;
      $mobilita = katataxeis_anno_scolastico_graduatoria($inviata);
      [$inizio] = explode('/', $mobilita);
      $in_corso = ($inizio - 1) . '/' . $inizio;

      // Già con l'anno della mobilità, o con un anno che non si riconosce.
      if ($scheda->anno !== $in_corso) {
        continue;
      }

      $riga = [
        $scheda->sid,
        self::NOMI_FORMULARI[$scheda->webform_id] ?? $scheda->webform_id,
        trim($scheda->cognome . ' ' . $scheda->nome),
        date('d/m/Y', $inviata),
        $scheda->anno,
        $mobilita,
      ];

      if (!empty($occupati[$scheda->webform_id][$scheda->uid][$mobilita])) {
        $saltate[] = $riga;
        continue;
      }

      unset($occupati[$scheda->webform_id][$scheda->uid][$scheda->anno]);
      $occupati[$scheda->webform_id][$scheda->uid][$mobilita] = TRUE;
      $da_spostare[$scheda->sid] = $riga;
    }

    $intestazione = ['Scheda', 'Formulario', 'Nome', 'Inviata il', 'Anno attuale', 'Anno nuovo'];
    $this->io()->title(dt("Katatáxeis: anno scolastico delle schede già inviate"));

    if ($saltate) {
      $this->io()->section(dt('Da sistemare a mano: la persona ha già una scheda con l\'anno nuovo (@n)', ['@n' => count($saltate)]));
      $this->io()->table($intestazione, $saltate);
    }

    if (!$da_spostare) {
      $this->io()->success(dt('Nessuna scheda da spostare.'));
      return;
    }

    $this->io()->section(dt('Schede da spostare: @n', ['@n' => count($da_spostare)]));
    $this->io()->table($intestazione, array_values($da_spostare));

    if (!$options['applica']) {
      $this->io()->note(dt('Non è stato modificato nulla. Per applicare: drush katataxeis:sposta-anno --applica'));
      return;
    }

    $transazione = $this->database->startTransaction();
    foreach ($da_spostare as $sid => $riga) {
      $this->database->update('webform_submission_data')
        ->fields(['value' => $riga[5]])
        ->condition('sid', $sid)
        ->condition('name', 'anno_scolastico')
        ->execute();
    }
    unset($transazione);

    // Cambia solo l'etichetta, direttamente nei dati: salvare le schede
    // ricalcolerebbe i punti con le formule di oggi.
    \Drupal::entityTypeManager()->getStorage('webform_submission')->resetCache(array_keys($da_spostare));
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['webform_submission_list']);

    $this->logger()->success(dt('Spostate @n schede.', ['@n' => count($da_spostare)]));
  }


  /**
   * Il nome breve di ogni formulario, per gli elenchi dei comandi.
   */
  const NOMI_FORMULARI = [
    'graduatoria_interna_ata' => 'ATA',
    'graduatoria_interna_docenti_inf' => 'Infanzia',
    'graduatoria_interna_docenti_pri' => 'Primaria',
    'graduatoria_interna_docenti_scu' => 'Secondaria I grado',
    'graduatoria_interna_docenti_sc2' => 'Secondaria II grado',
  ];

  /**
   * I formulari con la voce E già riscritta come esclusione dalla graduatoria.
   *
   * Per ciascuno: il nome da mostrare, il campo che distingue il personale
   * (profilo o posto), quanto vale la voce D e l'articolo del CCNI che regola
   * l'esclusione. Si aggiunge un formulario quando lo si rivede.
   */
  const FORMULARI_ESCLUSIONE = [
    'graduatoria_interna_ata' => [
      'nome' => 'ATA',
      'campo' => 'profilo',
      'etichetta' => 'Profilo',
      'punti_d' => 24,
      'articolo' => 'art. 40, comma 2',
    ],
    'graduatoria_interna_docenti_inf' => [
      'nome' => 'docenti della scuola dell\'infanzia',
      'campo' => 'posto_infanzia',
      'etichetta' => 'Posto',
      'punti_d' => 6,
      'articolo' => 'art. 13, comma 2',
    ],
    'graduatoria_interna_docenti_pri' => [
      'nome' => 'docenti della scuola primaria',
      'campo' => 'posto_primaria',
      'etichetta' => 'Posto',
      'punti_d' => 6,
      'articolo' => 'art. 13, comma 2',
    ],
    'graduatoria_interna_docenti_scu' => [
      'nome' => 'docenti della scuola secondaria di I grado',
      'campo' => 'posto_secondaria',
      'etichetta' => 'Posto',
      'punti_d' => 6,
      'articolo' => 'art. 13, comma 2',
    ],
    'graduatoria_interna_docenti_sc2' => [
      'nome' => 'docenti della scuola secondaria di II grado',
      'campo' => 'posto_secondaria',
      'etichetta' => 'Posto',
      'punti_d' => 6,
      'articolo' => 'art. 13, comma 2',
    ],
  ];

  /**
   * Elenca le schede da ricontrollare prima dell'aggiornamento.
   *
   * Fino alla versione precedente le viste trattavano come esclusi dalla
   * graduatoria sia chi aveva la voce D (cura e assistenza) sia chi aveva la
   * voce E (allora «disabilità personale»). Per il CCNI la voce D dà punti a
   * chi resta in graduatoria; l'esclusione è solo quella dell'art. 40, comma 2
   * (ATA) o dell'art. 13, comma 2 (docenti), che ora è la voce E. Aggiornando,
   * chi ha D = sì passa dagli esclusi alla graduatoria: il dirigente deve
   * verificare che non si tratti di chi assiste un familiare con disabilità
   * grave (legge 104), che avrebbe dovuto dichiarare E.
   *
   * Il comando legge soltanto: non modifica nessuna scheda.
   */
  #[CLI\Command(name: 'katataxeis:verifica-esclusioni', aliases: ['katataxeis-ve'])]
  #[CLI\Option(name: 'anno', description: "Limita l'elenco a un anno scolastico, per esempio 2025/2026.")]
  #[CLI\Usage(name: 'drush katataxeis:verifica-esclusioni', description: 'Elenca, per ogni formulario rivisto, le schede con la voce D o la voce E, per tutti gli anni.')]
  #[CLI\Usage(name: 'drush katataxeis:verifica-esclusioni --anno=2025/2026', description: 'Lo stesso, per un solo anno scolastico.')]
  public function verificaEsclusioni(array $options = ['anno' => NULL]): void {
    // Solo i formulari presenti: un comprensivo non ha il II grado, e viceversa.
    foreach (array_intersect_key(self::FORMULARI_ESCLUSIONE, katataxeis_formulari_installati()) as $webform_id => $formulario) {
      $this->verificaEsclusioniFormulario($webform_id, $formulario, $options['anno']);
    }
  }

  /**
   * Stampa le due sezioni della verifica per un formulario.
   *
   * @param string $webform_id
   *   Il formulario.
   * @param array $formulario
   *   La sua descrizione, da FORMULARI_ESCLUSIONE.
   * @param string|null $anno
   *   L'anno scolastico a cui limitarsi, o NULL per tutti.
   */
  protected function verificaEsclusioniFormulario($webform_id, array $formulario, $anno): void {
    $campi = [
      'anno_scolastico', 'cognome', 'nome', $formulario['campo'],
      'esigenze_famiglia_si_no_d', 'esigenze_famiglia_si_no_e',
      'esclusione_graduatoria_caso', 'verifica_graduatoria_ds',
    ];

    // Le schede con almeno una delle due voci a «si».
    $sids = $this->database->select('webform_submission_data', 'd')
      ->fields('d', ['sid'])
      ->condition('d.webform_id', $webform_id)
      ->condition('d.name', ['esigenze_famiglia_si_no_d', 'esigenze_famiglia_si_no_e'], 'IN')
      ->condition('d.value', 'si')
      ->distinct()
      ->execute()
      ->fetchCol();

    $schede = [];
    if ($sids) {
      $righe = $this->database->select('webform_submission_data', 'd')
        ->fields('d', ['sid', 'name', 'value'])
        ->condition('d.sid', $sids, 'IN')
        ->condition('d.name', $campi, 'IN')
        ->execute();
      foreach ($righe as $riga) {
        $schede[$riga->sid][$riga->name] = $riga->value;
      }
    }

    if ($anno) {
      $schede = array_filter($schede, fn($dati) => ($dati['anno_scolastico'] ?? '') === $anno);
    }

    // In ordine di anno scolastico, poi di cognome.
    uasort($schede, fn($a, $b) => [$a['anno_scolastico'] ?? '', $a['cognome'] ?? ''] <=> [$b['anno_scolastico'] ?? '', $b['cognome'] ?? '']);

    $intestazione = ['Scheda', 'A.S.', 'Cognome', 'Nome', $formulario['etichetta'], 'Caso', 'Verifica DS'];
    $tabella = function (array $elenco) use ($formulario) {
      $righe = [];
      foreach ($elenco as $sid => $dati) {
        $righe[] = [
          $sid,
          $dati['anno_scolastico'] ?? '',
          $dati['cognome'] ?? '',
          $dati['nome'] ?? '',
          $dati[$formulario['campo']] ?? '',
          $dati['esclusione_graduatoria_caso'] ?? '—',
          $dati['verifica_graduatoria_ds'] ?? '',
        ];
      }
      return $righe;
    };

    // Nella prima sezione solo chi si sposta davvero: chi ha anche E resta
    // escluso e compare nella seconda.
    $con_d = array_filter($schede, fn($dati) => ($dati['esigenze_famiglia_si_no_d'] ?? '') === 'si' && ($dati['esigenze_famiglia_si_no_e'] ?? '') !== 'si');
    $con_e = array_filter($schede, fn($dati) => ($dati['esigenze_famiglia_si_no_e'] ?? '') === 'si');

    $segnaposto = ['@nome' => $formulario['nome'], '@punti' => $formulario['punti_d'], '@articolo' => $formulario['articolo']];

    $this->io()->title(dt('Katatáxeis: schede @nome da verificare', $segnaposto) . ($anno ? ' — ' . $anno : ''));

    $this->io()->section(dt('Voce D = sì, senza la voce E (cura e assistenza, @punti punti): @n', $segnaposto + ['@n' => count($con_d)]));
    $this->io()->text(dt("Dopo l'aggiornamento queste persone NON sono più tra gli esclusi: entrano in graduatoria con i @punti punti della voce D. Verificare che non assistano un familiare con disabilità in situazione di gravità (legge 104): in quel caso devono dichiarare la voce E, caso IV, per restare escluse.", $segnaposto));
    if ($con_d) {
      $this->io()->table($intestazione, $tabella($con_d));
    }

    $this->io()->section(dt('Voce E = sì (esclusione dalla graduatoria): @n', ['@n' => count($con_e)]));
    $this->io()->text(dt("Queste persone restano escluse. Nella versione precedente la voce E era «disabilità personale»: alla prossima compilazione dovranno indicare il caso dell'@articolo, del CCNI; «—» significa che non l'hanno ancora indicato.", $segnaposto));
    if ($con_e) {
      $this->io()->table($intestazione, $tabella($con_e));
    }

    if (!$con_d && !$con_e) {
      $this->io()->success(dt('Nessuna scheda con la voce D o la voce E: l\'aggiornamento non sposta nessuno.'));
    }
  }

}
