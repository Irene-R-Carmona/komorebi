// Funciones helper usadas en el dashboard de cuidadores
globalThis.getStatusBadgeClass = function (status) {
  const classes = { active: 'success', resting: 'warning', sick: 'danger', retired: 'secondary' };
  return classes[status] || 'secondary';
};

globalThis.getStatusLabel = function (status) {
  const labels = { active: 'Activo', resting: 'Descansando', sick: 'Enfermo', retired: 'Retirado' };
  return labels[status] || status;
};

globalThis.getSeverityBadgeClass = function (severity) {
  const classes = { low: 'success', medium: 'warning', high: 'danger' };
  return classes[severity] || 'secondary';
};
