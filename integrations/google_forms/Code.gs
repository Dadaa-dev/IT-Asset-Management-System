/**
 * AssetVault Google Forms response synchronizer.
 *
 * Bind this Apps Script project to the Google Sheets response spreadsheet.
 * Set the Script Properties listed in README.md, then run installTrigger() once.
 */

function installTrigger() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (!spreadsheet) throw new Error('Open this script from the Google Forms response spreadsheet.');

  ScriptApp.getProjectTriggers()
    .filter(trigger => trigger.getHandlerFunction() === 'onFormSubmit')
    .forEach(trigger => ScriptApp.deleteTrigger(trigger));

  ScriptApp.newTrigger('onFormSubmit')
    .forSpreadsheet(spreadsheet)
    .onFormSubmit()
    .create();
}

function onFormSubmit(event) {
  if (!event || !event.namedValues || !event.range) {
    throw new Error('This function must be invoked by the installed spreadsheet form-submit trigger.');
  }

  const properties = PropertiesService.getScriptProperties();
  const endpoint = (properties.getProperty('ASSETVAULT_WEBHOOK_URL') || '').trim();
  const secret = (properties.getProperty('ASSETVAULT_WEBHOOK_SECRET') || '').trim();
  if (!endpoint || !secret) throw new Error('Set ASSETVAULT_WEBHOOK_URL and ASSETVAULT_WEBHOOK_SECRET in Script Properties.');

  const fields = event.namedValues;
  const value = (...labels) => {
    for (const label of labels) {
      const found = fields[label];
      if (found && found.length && String(found[0]).trim() !== '') return String(found[0]).trim();
    }
    return '';
  };

  // Change these labels only if your Google Form question titles differ.
  const responseId = event.range.getSheet().getSheetId() + ':' + event.range.getRow();
  const payload = {
    response_id: responseId,
    asset_tag: value('Asset Tag', 'Asset Code', 'Asset ID'),
    asset_name: value('Asset Name', 'Equipment Name'),
    location: value('Location', 'Room / Location', 'Campus Location'),
    concern: value('Problem / Concern', 'Problem', 'Description', 'Details'),
    reporter_name: value('Reporter Name', 'Name', 'Full Name')
  };

  if (!payload.concern) throw new Error('Could not find the Problem / Concern answer. Check the question title aliases in Code.gs.');

  const response = UrlFetchApp.fetch(endpoint, {
    method: 'post',
    contentType: 'application/json',
    headers: { Authorization: 'Bearer ' + secret },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  });

  const status = response.getResponseCode();
  const body = response.getContentText();
  if (status < 200 || status >= 300) {
    throw new Error('AssetVault rejected the report (' + status + '): ' + body);
  }
  console.log('AssetVault import result: ' + body);
}
