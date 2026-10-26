# Details zu Bewertungsaspekten (local_gradeitemdetail)

Ergänzt **manuelle Bewertungsaspekte** um ein zusätzliches Textfeld. Der Text wird
Teilnehmer/innen in der Bewertungsübersicht als Pop-up angezeigt, wenn sie auf den
Namen des Bewertungsaspekts klicken.

Getestet gegen Moodle 5.1 (`$plugin->requires` ist auf 4.5 gesetzt).

## Was das Plugin macht

**Für Lehrkräfte** – *Bewertungen → Setup für Bewertungen → … → Bewertungsaspekt bearbeiten*
(`/grade/edit/tree/item.php`): direkt unter „Info zum Aspekt" erscheint das neue Feld
**„Details für Teilnehmer/innen"** mit dem normalen Moodle-Editor (Formatierung, Links,
Bilder). Es wird zusammen mit dem übrigen Formular gespeichert.

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

## Bekannte Einschränkungen

- **Kein Backup/Restore.** Die Texte hängen an `grade_items`, und Moodle sieht für
  Bewertungsaspekte keine Backup-Erweiterung durch lokale Plugins vor. Beim Sichern,
  Duplizieren oder Importieren eines Kurses gehen sie verloren. Nachrüstbar, aber
  aufwendig (Course-Level-Backup plus verzögertes Mapping der Grade-Item-IDs nach dem
  Gradebook-Restore-Schritt).
- **Ohne JavaScript** erscheint das Feld nicht im Formular und wird folglich nicht
  gespeichert. Bereits gespeicherte Texte bleiben unberührt.
- Das Feld hängt an der DOM-Struktur des Core-Formulars (`#fitem_id_iteminfo`). Bei
  einem größeren Umbau des Gradebooks in einer künftigen Moodle-Version ist das der
  Punkt, der zuerst nachgezogen werden muss. Findet das Skript den Anker nicht, bleibt
  das Feld unten auf der Seite stehen, statt zu verschwinden.

## Installation

Verzeichnis als `local/gradeitemdetail` in die Moodle-Installation legen
(bei Moodle 5.x: `public/local/gradeitemdetail`), dann *Website-Administration →
Mitteilungen* aufrufen und die Datenbankaktualisierung bestätigen. Anschließend
**alle Caches löschen**, damit das AMD-Modul ausgeliefert wird.

## Lizenz

GNU GPL v3 oder später – siehe [LICENSE](LICENSE).
