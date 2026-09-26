# Ticket calendar history

After uploading this module update, run `php artisan migrate` and `php artisan view:clear`.
The new migration adds provider links, creator information, and native note IDs to calendar events.
Until it has run, event creation returns a clear migration message before contacting a provider.

- Linked appointments appear below customer information for agents who can view that ticket.
- New appointments create a native internal note with event dates, timezone, author, and recorded time.
  The note is not sent as a customer reply. The sidebar and a preview of the saved note update immediately;
  FreeScout displays the native note normally on the next page load.
- Existing local appointments appear automatically. For Google appointments made by older versions,
  select **Find earlier Google events** in the ticket sidebar. It searches your saved default Google calendar
  for the exact ticket URL and records matching timed, non-recurring events without recreating them.
  The note identifies who linked the older appointment; the sidebar displays Google's original creator and
  creation time. Historical Microsoft appointments are not automatically recovered.
- Provider event cards display the recorded event times. Changes/deletions made later in Google or Outlook
  are not synchronized into this ticket history; open the provider link for the current event. Local edits
  appear when the sidebar is refreshed, while the original creation note remains an audit record.
- If provider creation succeeds but saving local history fails, the UI reports that partial success and
  prevents accidental duplicate submission. Check server logs instead of creating the event again.
- Reply badges are displayed below customer email addresses in mailbox rows. They use FreeScout's latest
  public reply direction, so internal calendar notes do not turn customer replies into agent replies.

Browser regression tests (with Playwright available on `NODE_PATH`):

```
node Modules/Freescout-QoL/Tests/ticket-calendar.cjs
node Modules/Freescout-QoL/Tests/calendar-panel.cjs
node Modules/Freescout-QoL/Tests/calendar-default.cjs
```

These tests mock API responses. Migration execution, native Thread persistence/observers, and real provider
calls must also be checked on a staging FreeScout installation; this repository has no runnable host app.
