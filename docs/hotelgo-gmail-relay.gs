/*
 * HOTELGO free email sender (Google Apps Script).
 * It sends emails from YOUR Gmail account. Free limit for a normal Gmail account: 100 recipients per day.
 *
 * 1) Change SECRET below to a long random text (the same text goes into APPS_SCRIPT_SECRET on Render / in your .env).
 * 2) Deploy > New deployment > type: Web app > Execute as: Me > Who has access: Anyone > Deploy.
 * 3) Copy the Web app URL (ends with /exec) into APPS_SCRIPT_URL.
 */
const SECRET = 'PUT-A-LONG-RANDOM-SECRET-HERE';

function doPost(e) {
  try {
    const d = JSON.parse(e.postData.contents);
    if (!d.secret || d.secret !== SECRET) return out({ ok: false, error: 'forbidden' });
    if (!d.to || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(d.to)) return out({ ok: false, error: 'bad recipient' });
    const options = { name: String(d.fromName || 'HOTELGO').slice(0, 60) };
    if (d.html) options.htmlBody = d.html;
    if (d.attachments && d.attachments.length) {
      options.attachments = d.attachments.slice(0, 3).map(function (a) {
        return Utilities.newBlob(Utilities.base64Decode(a.content), a.mime || 'application/octet-stream', a.filename);
      });
    }
    MailApp.sendEmail(d.to, String(d.subject || '').replace(/[\r\n]+/g, ' ').slice(0, 200), String(d.text || ''), options);
    return out({ ok: true, remaining: MailApp.getRemainingDailyQuota() });
  } catch (err) {
    return out({ ok: false, error: String(err).slice(0, 200) });
  }
}

function out(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
