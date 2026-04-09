document.addEventListener('alpine:init', () => {
    Alpine.data('receptionApp', () => ({
        // Estado
        checkinOpen: false,
        selectedResId: null,

        // Métodos
        openCheckin(reservationId) {
            console.log('Open checkin for:', reservationId);
            this.selectedResId = reservationId;
            this.checkinOpen = true;

            // Foco en el select para accesibilidad
            this.$nextTick(() => {
                const select = document.querySelector('select[name="tracker_id"]');
                if (select) select.focus();
            });
        },

        closeCheckin() {
            this.checkinOpen = false;
            this.selectedResId = null;
        }
    }));
});