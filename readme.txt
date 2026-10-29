=== IFCF7 Submissions to DataTables ===
Contributors: ifwp
Donate link: http://luisdelcid.com
Tags: ifcf7-std
Requires at least: 5.0
Tested up to: 7.1.4
Requires PHP: 5.6
Stable tag: 6.10.29
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse, filter and export IFCF7 submissions with jQuery DataTables (server-side processing).

== Description ==

Adds a standalone screen where every submission stored by IFCF7 is listed on a DataTables
table using server side processing, so the amount of stored submissions does not slow the
page down.

Columns are discovered by the plugin itself:

* Post fields: ID, date, title, status, author, content, excerpt, and so on.
* The contact form and the page the submission came from.
* Every named form tag of every contact form.
* UTM parameters.
* Every other meta key already stored by the submissions, hidden inputs included.

SearchBuilder knows the type of each column (date, num, string), and the backend routes each
criteria to the right place: post fields never go into `meta_query`, dates are filtered with
`date_query`, and the rest of the post fields are filtered against `wp_posts` directly.

There is one SearchBuilder panel per section on the same table: post, form, tags, utm, submission
and meta. Every panel builds its own criteria group, and the groups are ANDed together. Which
panels exist, and which columns belong to each one, is up to the `ifcf7_sdt_search_builder_groups`
filter and the `group` property of every column.

The form panel offers a single column, the contact form of the submission: its id, name, title and
locale all mean the same form, so only the first one is filterable. What the submissions do store
under it is the id, but what it filters by is the title, as a text: "contains", "starts with" and
friends do work, "equals" and "not equals" offer the list of every form there is, and whatever the
text does match is resolved into the ids to filter by, all of them at once where several forms do
carry the very same title. Nothing but the list of forms is read to do that, and it is loaded
already. The id is still accepted by "equals" and "not equals", which is what the views saved
before this column became a text do hold: those are rewritten into titles when the view opens, and
saving it again makes it permanent.

Picking a contact form in the form panel shows its fields and hides the ones belonging to other
forms, in the table and in the dropdown of the tags panel alike: the columns of a search builder
are resolved every time it populates one, so a function selector does keep them in sync. It reads the "equals" and "not equals" criteria of that panel, it leaves every other column
alone, and it only ever runs when the forms being filtered by do change: paging, sorting or the
columns of a saved view are none of its business. The `ifcf7_sdt_auto_sync` filter set to false
turns it back into a button of its own next to the other ones.

The panels live in a sliding sidebar, behind a single button that shows how many criteria are
active. Set the `ifcf7_sdt_sidebar` filter to false to lay them out above the table instead.

The plain search box, the one looking for a text in every column at once, is off by default: it
means a `LIKE '%...%'` over the post fields plus an `EXISTS` over every searchable meta key, which
no index can help with, on as many submissions as there are. The `ifcf7_sdt_search` filter
returning true brings it back. The search of a request is gated by it as well, so nothing filters
the table behind your back while the box is gone; a view saved while it was there does keep its
text, unapplied, waiting for the day it is turned on again.

Inside a panel, the dropdown groups its options by section by prefixing every label with its
section name: `post` (the post fields), `form` (the contact form itself), `tags` (the scanned
form tags), `utm`, `submission` (what IFCF7 stores about the submission itself: ip, user agent,
timestamp, response...) and `meta` (every other meta key found on the submissions, the ones added
with `update_post_meta` somewhere in the business logic of the site, which Contact Form 7 never
heard of; no type is known for those, so they are plain text). Columns are
emitted in that same order, so every section stays in one piece.

SearchBuilder is unable to render real optgroups: it reads the selected column with
`children('option:selected')`, which does not see options nested in one.

Columns with a known set of values offer a dropdown instead of a text field for the "equals" and
"not equals" conditions: the contact form of the submission (id, title, name and locale), the post
status, the users who did author a submission (labelled by their login), and every select, radio
or checkbox form tag, its data options (posts, terms, users, catalogues) included. Every other
condition ("contains", "starts with", ...) stays a text field, as it should. The
`ifcf7_sdt_options` filter takes `alias => [['label' => ..., 'value' => ...], ...]`.

A column whose values go over `ifcf7_sdt_max_data_options` (1000) or `ifcf7_sdt_max_authors` (200)
gets no dropdown at all, and a plain text field instead. Never a truncated list: it would look
complete, and quietly hide whatever did not fit.

Those value dropdowns are searchable: Select2 4.0.13 with the bootstrap theme, the very versions
and handles IFCF7 does use, so neither of them loads it twice. The `ifcf7_sdt_select2` filter
turns it off. The data and condition dropdowns are left alone on purpose: SearchBuilder empties
and repopulates those in place, which would leave Select2 showing options that are gone.

Beware that under the name of a form tag IFCF7 does store the label the user saw, not the value
behind a pipe or a data option: that one goes to `raw_{name}`, a column of its own.

Setting `filterable` to false on a column keeps it in the table, and out of the search builders.
A group whose columns are all unfilterable gets no panel at all.

= Where is the data? =

`/ifcf7-submissions/` (or `/?ifcf7_sdt=1` when permalinks are plain), also linked from the
Contact Form 7 admin menu. There is no page to create: the plugin registers the URL itself.

Only users with the `edit_others_posts` capability are allowed in, and downloading the data is off
until the `ifcf7_sdt_export` filter says otherwise.

= Saved views =

The "Create view" button of the sidebar does store the criteria of every search builder, the value
of the search box, the contact forms in scope and the visible columns as a `ifcf7-sdt-view` post,
and takes you to it. A view opens with its own columns, and the saved state is not allowed to put
its own back: to change them, change them and save the view again.
Saving is off while no panel does hold a single criteria: a view with no filters is the general
table under another name. Deleting is not, it has nothing to do with the filters.
While looking at a view, that button becomes "Save as", and a "Save" and a "Delete" one show up
next to it, to overwrite or to remove the view being looked at. Each one only shows up for users
who can edit, or delete, that very post. Deleting takes you back to the general table, and moves
the view to the trash rather than removing it for good, unless `ifcf7_sdt_force_delete_view`
says otherwise.

* `/ifcf7-submissions/` is the general table, unchanged.
* `/ifcf7-submissions/<view slug>/` is the same table with those filters already applied.

Both go through the very same capability check, and the post type is not publicly queryable: the
only way in is this page. The criteria live in a protected `_ifcf7_sdt_criteria` meta, never in
the post content, so nothing shows up in searches, feeds, sitemaps or the REST API.

Views are listed in a dropdown of the navigation bar, and under Contact Form 7 in the admin, where
they can be renamed and deleted.

= Optional shortcode =

`[ifcf7_sdt_table form_id="123,456" bootstrap="0"]`

* `form_id`: only these contact forms. Empty means all of them.
* `bootstrap`: `1` loads Bootstrap 4 from a CDN, `0` (default) expects the theme to load it.

The standalone screen accepts `form_id` too: `/ifcf7-submissions/?form_id=123,456`.

= Filters =

* `ifcf7_sdt_capability`: capability needed to see the data. Default `edit_others_posts`.
* `ifcf7_sdt_export`: turns the download of personal data on, off by default. Both the button and
  the request itself are gated by it, so returning `current_user_can(...)` does hand it over to
  some users only.
* `ifcf7_sdt_search`: the search box over every column at once, off by default. Both the box and
  the search of a request are gated by it.
* `ifcf7_sdt_slug`: the standalone screen slug. Default `ifcf7-submissions`.
* `ifcf7_sdt_columns`: the whole column list, before it is parsed. Every column accepts a `group`
  key, which is the search builder panel it shows up in. Defaults to its source: `post` or `meta`.
* `ifcf7_sdt_search_builder_groups`: `group => title` of every search builder panel, in order.
* `ifcf7_sdt_sections`: `section => label` of the option groups of the dropdown.
* `ifcf7_sdt_helper_fields`: `meta key => label, section` of the metadata IFCF7 stores by itself.
* `ifcf7_sdt_search_builder_title`, `ifcf7_sdt_section_separator`: the label of every option.
* `ifcf7_sdt_post_fields`: which `wp_posts` fields become columns.
* `ifcf7_sdt_post_types`, `ifcf7_sdt_post_statuses`: what counts as a submission.
* `ifcf7_sdt_meta_keys`, `ifcf7_sdt_max_meta_keys`, `ifcf7_sdt_meta_keys_expiration`: meta key discovery.
* `ifcf7_sdt_excluded_prefixes`: meta keys never worth a column, `ak_` (Akismet), `uploaded_id_`
  and `uploaded_error_` by default. Both this one and `ifcf7_sdt_meta_keys` are applied on every
  read, not on the cached discovery, so they take effect right away.
* `ifcf7_sdt_authors`, `ifcf7_sdt_max_authors`: the users offered by the author dropdown.
* `ifcf7_sdt_column_value`: the rendered value of a cell.
* `ifcf7_sdt_empty_value`: what a cell with no value shows, nothing at all by default. Exports
  ignore it and stay empty.
* `ifcf7_sdt_query_args`: the resulting WP_Query arguments.
* `ifcf7_sdt_meta_query`: false routes the metadata criteria through EXISTS subqueries in
  `posts_where` instead of `meta_query`. No joins, so no rows to deduplicate: worth trying when a
  filtered query is slow.
* `ifcf7_sdt_assets`, `ifcf7_sdt_language_url`, `ifcf7_sdt_settings`: front end assets and DataTables settings.
* `ifcf7_sdt_page_length`, `ifcf7_sdt_state_save`, `ifcf7_sdt_date_format`, `ifcf7_sdt_title`.
* `ifcf7_sdt_colvis_layout`: collectionLayout of the column visibility lists, "fixed columns" by
  default. An empty string brings the plain dropdown back.
* `ifcf7_sdt_colvis_groups`: one column visibility list per group of the search builders, behind a
  dropdown of groups. On by default, and skipped anyway when there is a single group.
* `ifcf7_sdt_auto_sync`: syncing the columns to the contact forms being filtered by. On by
  default; false brings the "Sync columns" button back instead.
* `ifcf7_sdt_max_views`, `ifcf7_sdt_max_criteria_length`: limits of the saved views.
* `ifcf7_sdt_export_chunk`, `ifcf7_sdt_export_max_posts`, `ifcf7_sdt_export_expiration`, `ifcf7_sdt_export_sheet`.

= Actions =

* `ifcf7_sdt_head`, `ifcf7_sdt_footer`, `ifcf7_sdt_after_table`.
* `ifcf7_sdt_insert_view`: right after a view is saved.

== Changelog ==

= 6.5.26 =
* First release.
