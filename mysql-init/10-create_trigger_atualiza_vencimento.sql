-- Script para MySQL
DELIMITER //

CREATE TRIGGER trg_atualiza_vencimento
BEFORE UPDATE ON usuario
FOR EACH ROW
BEGIN
    IF OLD.pagamento_inicial = 0 AND NEW.pagamento_inicial = 1 THEN
        SET NEW.data_vencimento_assinatura = DATE_ADD(CURDATE(), INTERVAL 60 DAY);
    END IF;
END;
//

DELIMITER ;
