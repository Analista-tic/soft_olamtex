export function formatRequiredFieldsMessage(baseMessage: string, fields: string[]) {
  if (!fields?.length) {
    return baseMessage;
  }

  const normalizedFields = fields
    .map((field) => field.trim())
    .filter(Boolean)
    .map((field) => (field.endsWith('*') ? field : `${field}*`));

  if (!normalizedFields.length) {
    return baseMessage;
  }

  const trimmedBase = baseMessage.trim().replace(/[:.]+$/, '');
  return `${trimmedBase}: ${normalizedFields.join(', ')}.`;
}
