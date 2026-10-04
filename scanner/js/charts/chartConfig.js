/**
 * Purpose: Publish chart palette constants used by the Scanner.
 * Loaded by: scanner/index.php as a classic script.
 * Inputs/outputs: No inputs; assigns IRISChartConfig to window.
 * Dependencies: None.
 * Load order: Load before chart modules that read IRISChartConfig.
 */
(function (root) {
  root.IRISChartConfig = {
    palettes: {
      QS: ['#1E6031', '#62B37B'],
      AppliedHE: ['#B7791F', '#F0C36A'],
      default: ['#0F766E', '#5EEAD4']
    },
    colors: ['#1E6031', '#B7791F', '#0F766E', '#2563EB', '#C2410C', '#7C3AED']
  };
})(window);
