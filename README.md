# Details zu Bewertungsaspekten (local_gradeitemdetail)

Ergänzt **manuelle Bewertungsaspekte** um ein zusätzliches Textfeld. Der Text wird
Teilnehmer/innen in der Bewertungsübersicht als Pop-up angezeigt, wenn sie auf den
Namen des Bewertungsaspekts klicken.

Getestet gegen Moodle 5.1 (`$plugin->requires` ist auf 4.5 gesetzt).

## Was das Plugin macht

**Für Lehrkräfte** gibt es zwei Wege zum Feld **„Details für Teilnehmer/innen"**, beide
mit dem normalen Moodle-Editor (Formatierung, Links, Bilder):

- *Bewertungen → Setup für Bewertungen* (und ebenso die *Bewerterübersicht*): Im
  Aktionsmenü jedes manuellen Bewertungsaspekts steht direkt unter „Bewertungsaspekt
  bearbeiten" der Eintrag **„Details für Teilnehmer/innen bearbeiten"**. Er öffnet den
  Text in einem eigenen Modal und speichert ihn sofort.
- Die Vollseite *Bewertungsaspekt bearbeiten* (`/grade/edit/tree/item.php`, im
  Core-Modal über „Mehr anzeigen…"): Dort steht das Feld direkt unter „Info zum Aspekt"
  und wird zusammen mit dem übrigen Formular gespeichert. Nur hier lässt sich der Text
  schon beim Anlegen eines neuen Aspekts erfassen.

**Für Teilnehmer/innen** – *Bewertungen → Übersicht für Teilnehmer/in*
(`/grade/report/user/index.php`, ebenso `/course/user.php?mode=grade`): Namen manueller
Bewertungsaspekte mit hinterlegtem Text werden zu einer Schaltfläche mit Info-Symbol.
Ein Klick öffnet den Text in einem Modal.

Aktivitäts-Bewertungsaspekte (Aufgabe, Test, …) bleiben unangetastet – dort setzt Moodle
bereits einen eigenen Link auf die Aktivität, mit dem sich das Pop-up sonst überschneiden
würde. Kategorie- und Kurs-Aspekte ebenfalls nicht.

## Einstellung

*Website-Administration → Plugins → Lokale Plugins → Details zu Bewertungsaspekten*

- **Details in der Teilnehmerübersicht anzeigen** (Standard: an) – schaltet nur die
  Anzeige ab; bereits erfasste Texte bleiben erhalten.

## Wie es technisch funktioniert

Moodle-Core bietet für `edit_item_form` keinen Erweiterungspunkt – weder einen Hook noch
einen Callback. Das Plugin geht deshalb so vor:

1. Im Hook `before_footer_html_generation` wird ein echtes
   `MoodleQuickForm_editor`-Element serverseitig gerendert (inklusive Entwurfsbereich für
   Dateien) und in den Seitenfuß geschrieben.
2. Ein kurzes Inline-Skript verschiebt das Feld synchron – also bevor irgendein
   AMD-Modul startet – in das Core-Formular unter `#fitem_id_iteminfo`. Würde man den
   Editor erst nach seiner Initialisierung verschieben, lüde der Browser dessen iframe
   neu und der Inhalt ginge verloren.
3. Beim Absenden wird das Feld ganz normal mit dem Formular gepostet. Ein Observer auf
   `\core\event\grade_item_created` / `grade_item_updated` greift es in genau dem Moment
   ab, in dem Core den Bewertungsaspekt speichert. So wird der Text nur gespeichert, wenn
   das umgebende Formular auch validiert hat – und ein neu angelegter Aspekt funktioniert
   ebenfalls, denn dessen ID existiert erst dann.

`\core\event\grade_item_deleted` räumt Datensatz und eingebettete Dateien wieder ab.

Das Core-Modal „Bewertungsaspekt bearbeiten" (`core_grades\form\add_item`) hat keinen
Erweiterungspunkt und speichert über einen Webservice, an dessen Formulardaten ein
Observer nicht herankommt. Deshalb hat der Text dort ein eigenes Formular
(`local_gradeitemdetail\form\detail`). Das AMD-Modul `local_gradeitemdetail/setup`
setzt den Menüeintrag neben Cores Eintrag `[data-trigger="add-item-form"]` jedes
manuellen Aspekts.

### Backup und Wiederherstellung

Die Texte hängen am `grade_item`-Element von `gradebook.xml`
(`backup/moodle2/`), samt eingebetteter Dateien. Sie wandern damit bei Kurssicherung,
Wiederherstellung und Kurskopie mit – und fehlen in genau den Fällen, in denen Moodle
selbst das Gradebook nicht wiederherstellt (etwa beim Zusammenführen in einen Kurs mit
eigener Kategorienstruktur oder wenn Aktivitäten abgewählt wurden). Wer einen Text
zuletzt geändert hat, wird nicht mitgesichert.

## Bekannte Einschränkungen

- **Ohne JavaScript** gibt es weder den Menüeintrag noch das Feld auf der Vollseite.
  Bereits gespeicherte Texte bleiben unberührt.
- Beide Wege hängen an Cores DOM-Struktur: der Menüeintrag an
  `[data-trigger="add-item-form"]`, das Feld auf der Vollseite an `#fitem_id_iteminfo`
  (ersatzweise am Formular selbst). Bei einem größeren Umbau des Gradebooks in einer
  künftigen Moodle-Version sind das die Punkte, die zuerst nachgezogen werden müssen.
  Findet das Skript auf der Vollseite kein Formular, blendet es das Feld aus, statt
  Eingaben anzunehmen, die nicht gespeichert würden.

## Installation

Verzeichnis als `local/gradeitemdetail` in die Moodle-Installation legen
(bei Moodle 5.x: `public/local/gradeitemdetail`), dann *Website-Administration →
Mitteilungen* aufrufen und die Datenbankaktualisierung bestätigen. Anschließend
**alle Caches löschen**, damit das AMD-Modul ausgeliefert wird.

## Lizenz

GNU GPL v3 oder später – siehe [LICENSE](LICENSE).
