# Katatáxeis

Katatáxeis (dal greco «graduatorie») gestisce le **graduatorie interne d'istituto** della scuola: le schede con cui il personale dichiara servizio, esigenze di famiglia e titoli, il calcolo del punteggio e le graduatorie che individuano i soprannumerari (perdenti posto).

Riferimento normativo: **CCNI sulla mobilità 2025/26 - 2027/28, testo definitivo firmato il 10 marzo 2026**. Il PDF è nel modulo (`documenti/`) e si consulta dal sito alla pagina `/admin/graduatorie-interne/normativa`, con i collegamenti diretti agli articoli e alle note a cui rimandano le schede.

## Moduli

| Modulo | Contiene | Chi lo attiva |
|---|---|---|
| `katataxeis` | formulario e graduatorie del personale ATA, normativa, pagine di elenco, comandi drush | tutte le scuole |
| `katataxeis_comprensivo` | formulari e graduatorie dei docenti di infanzia, primaria e secondaria di I grado | istituti comprensivi |
| `katataxeis_secondo_grado` | formulario e graduatorie dei docenti della secondaria di II grado; le classi di concorso vengono dal vocabolario «Materie» (codice MIUR in `field_miur_class_id`) | scuole secondarie di II grado |

Un istituto omnicomprensivo attiva entrambi i sottomoduli. Il formulario del II grado può essere compilato dai docenti con il ruolo `doc_secondaria_secondo_grado`.

Disinstallando un sottomodulo si cancellano **definitivamente** i suoi formulari con tutte le schede inviate, le sue viste e le sue liste di opzioni: prima conviene esportare le graduatorie. Il modulo principale si disinstalla solo dopo i sottomoduli.

## Come funziona

- Formulari e viste stanno in `config/update` (del modulo e di ciascun sottomodulo) e si riallineano alla versione del modulo a ogni `drush updb`, oppure con `drush katataxeis:config-update`.
- Le liste di opzioni stanno in `config/optional`: si installano una volta sola e poi ogni scuola le adatta alla propria organizzazione. Il modulo non le sovrascrive mai.
- Il punteggio si calcola nel formulario, voce per voce, senza correzioni a mano; il dirigente spunta la «Verifica DS» su ogni voce.
- La scheda nuova propone da sola l'anno scolastico della mobilità per cui si compila (a marzo 2027: 2027/2028).
- Graduatorie ordinate come prevede il CCNI: in coda chi è entrato nell'organico dal 1° settembre scorso per domanda volontaria o immissione in ruolo, poi per punteggio e, a parità, per età (più anziano prima). Nella secondaria una graduatoria per classe di concorso; sul sostegno di infanzia, primaria e I grado una per tipologia (psicofisici, vista, udito); nella primaria anche lingua inglese ed educazione motoria.
- Nelle pagine di consultazione il personale vede la graduatoria del proprio ordine (solo schede verificate, solo il totale) senza poter aprire le schede dei colleghi.

## Aggiornare una scuola

Dalla versione 2.1.0, nelle scuole che già usano il modulo:

1. fare una copia del database;
2. caricare il modulo;
3. `drush katataxeis:verifica-esclusioni` (`katataxeis-ve`): elenca, in sola lettura, le schede con la voce D (cura e assistenza) o E (esclusione). Dopo l'aggiornamento la voce D non esclude più dalla graduatoria: il dirigente verifica l'elenco;
4. `drush updb`: attiva da solo `katataxeis_comprensivo` dove ci sono i formulari di infanzia, primaria e I grado, aggiunge alla lista dei posti della primaria «Lingua inglese» ed «Educazione motoria» (solo se mancano) e riallinea formulari e viste;
5. `drush katataxeis:sposta-anno` (`katataxeis-sa`): mostra le schede che portano l'anno in corso invece di quello della mobilità; con `--applica` le sposta di un anno (cambia solo l'etichetta, non i punti).

In una scuola secondaria di II grado, dopo l'aggiornamento: `drush en katataxeis_secondo_grado`.
