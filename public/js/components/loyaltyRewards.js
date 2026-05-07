// Componente centralizado: loyaltyRewards
// Exporta la fábrica en window.loyaltyRewards
(function () {
  'use strict';

  globalThis.loyaltyRewards = function () {
    return {
      async redeemReward(rewardType) {
        if (!confirm('¿Confirmas que deseas canjear esta recompensa?')) return;
        try {
          const csrfMeta = document.querySelector('meta[name="csrf-token"]');
          const response = await fetch('/api/v1/loyalty/redeem', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': (csrfMeta && csrfMeta.content) || '' },
            body: JSON.stringify({ reward_type: rewardType })
          });
          const result = await response.json();
          if (result && result.ok && result.data) {
            alert(`✨ ¡Recompensa canjeada!\n\nTu código: ${result.data.code}\n\nExpira: ${result.data.expires_at}`);
            window.location.reload();
          } else {
            alert('Error: ' + ((result && result.error) || 'No se pudo canjear'));
          }
        } catch (error) {
          console.error('redeemReward error:', error);
          alert('Error de conexión. Por favor, intenta de nuevo.');
        }
      }
    };
  };

})();
