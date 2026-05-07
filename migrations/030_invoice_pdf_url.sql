-- 030: Añadir columna invoice_pdf_url a reservations
-- Almacena la URL pública de Cloudinary del PDF de factura generado.
-- NULL cuando no se ha generado aún (reservas previas a esta migración).

ALTER TABLE reservations
    ADD COLUMN invoice_pdf_url VARCHAR(500) NULL AFTER status;
