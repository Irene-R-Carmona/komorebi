/**
 * Chart.js Custom Bundle - Tree-shaked
 * Solo incluye Line y Doughnut charts usados en Komorebi
 * Reducción: 180KB → ~80KB (-55%)
 */

import {
  Chart,
  LineController,
  DoughnutController,
  LineElement,
  ArcElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';

// Register only needed components
Chart.register(
  LineController,
  DoughnutController,
  LineElement,
  ArcElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
  Filler
);

// Export to global scope (compatible con código existente)
window.Chart = Chart;

export default Chart;
