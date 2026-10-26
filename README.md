# Grade item details (local_gradeitemdetail)

Adds an extra text field to **manual grade items**. Participants see the text in a pop-up when they click the
name of the grade item in their grade overview.

## Requirements

- **Moodle 4.5 or later** (`$plugin->requires = 2024100700`). Tested against Moodle 5.1.
- JavaScript must be enabled in the browser (see *Known limitations*).

## What the plugin does

**Teachers** have two ways to reach the **"Details for participants"** field, both with the standard Moodle
editor (formatting, links, images):

- *Grades → Gradebook setup* (and likewise the *Grader report*): in the action menu of every manual grade item,
  directly below "Edit grade item", there is an entry **"Edit details for participants"**. It opens the text in
  a modal of its own and saves it straight away.
- The full *Edit grade item* page (`/grade/edit/tree/item.php`, reached from core's modal via "Show more…"):
  there the field sits directly below "Item info" and is saved together with the rest of the form. This is the
  only place where the text can be entered while creating a new grade item.

**Participants** – *Grades → User report* (`/grade/report/user/index.php`, likewise
`/course/user.php?mode=grade`): names of manual grade items that have a text become a button with an info icon.
Clicking it opens the text in a modal.

Activity grade items (assignment, quiz, …) are left alone – Moodle already links them to their activity, and the
pop-up would clash with that link. Category and course items are not changed either.

## Settings

*Site administration → Plugins → Local plugins → Grade item details*

- **Show details in the participant overview** (default: on) – only switches off the display; texts that have
  already been entered are kept.

## How it works

Moodle core offers no extension point for `edit_item_form` – neither a hook nor a callback. The plugin therefore
works like this:

1. In the `before_footer_html_generation` hook, a real `MoodleQuickForm_editor` element is rendered on the
   server (including a draft area for files) and written into the page footer.
2. A short inline script moves the field synchronously – before any AMD module starts – into the core form below
   `#fitem_id_iteminfo`. Moving the editor after it has initialised would make the browser reload its iframe and
   lose the content.
3. On submit, the field is posted with the form as usual. An observer on `\core\event\grade_item_created` /
   `grade_item_updated` picks it up at exactly the moment core saves the grade item. That way the text is only
   stored if the surrounding form validated – and a newly created grade item works too, because its id only
   exists at that point.

`\core\event\grade_item_deleted` removes the record and the embedded files again.

Core's "Edit grade item" modal (`core_grades\form\add_item`) has no extension point and saves through a web
service whose form data an observer cannot reach. That is why the text has a form of its own there
(`local_gradeitemdetail\form\detail`). The AMD module `local_gradeitemdetail/setup` places the menu entry next to
core's `[data-trigger="add-item-form"]` entry of every manual grade item.

### Backup and restore

The texts are attached to the `grade_item` element of `gradebook.xml` (`backup/moodle2/`), together with their
embedded files. They therefore travel with course backups, restores and course copies – and are missing in exactly
the cases in which Moodle itself does not restore the gradebook (for example when merging into a course with its
own category structure, or when activities have been deselected). Who last changed a text is not backed up.

## Known limitations

- **Without JavaScript** there is neither the menu entry nor the field on the full page. Texts that have already
  been saved are not affected.
- Both ways depend on core's DOM structure: the menu entry on `[data-trigger="add-item-form"]`, the field on the
  full page on `#fitem_id_iteminfo` (falling back to the form itself). If a future Moodle version reworks the
  gradebook substantially, these are the first places to adapt. If the script finds no form on the full page, it
  hides the field rather than accept input that would not be saved.

## Installation

From Moodle 5.1, plugins live in the `public` directory:

```bash
cp -r local_gradeitemdetail /path/to/moodle/public/local/gradeitemdetail
php admin/cli/upgrade.php
```

On Moodle 4.5 and 5.0 the target is `/path/to/moodle/local/gradeitemdetail`. Alternatively, open
*Site administration → Notifications* and confirm the database upgrade. Afterwards **purge all caches** so the
AMD modules are delivered.

## License

GNU GPL v3 or later – see [LICENSE](LICENSE).
