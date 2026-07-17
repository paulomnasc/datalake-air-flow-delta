DELIMITER //

CREATE TRIGGER after_item_doc_insert
AFTER INSERT ON item_documento_recebimento
FOR EACH ROW
BEGIN
    DECLARE v_id_servico INT;
    DECLARE v_remuneracao FLOAT;
    
    SELECT s.id, s.remuneracao INTO v_id_servico, v_remuneracao
    FROM item_os io
    JOIN servico s ON io.id_servico = s.id
    WHERE io.id = NEW.id_item_os;

    UPDATE servico
    SET saldo_horas = saldo_horas - (NEW.quantidade_entregue * v_remuneracao)
    WHERE id = v_id_servico;
END //

CREATE TRIGGER after_item_doc_update
AFTER UPDATE ON item_documento_recebimento
FOR EACH ROW
BEGIN
    DECLARE v_id_servico INT;
    DECLARE v_remuneracao FLOAT;
    
    SELECT s.id, s.remuneracao INTO v_id_servico, v_remuneracao
    FROM item_os io
    JOIN servico s ON io.id_servico = s.id
    WHERE io.id = NEW.id_item_os;

    UPDATE servico
    SET saldo_horas = saldo_horas + (OLD.quantidade_entregue * v_remuneracao) - (NEW.quantidade_entregue * v_remuneracao)
    WHERE id = v_id_servico;
END //

CREATE TRIGGER after_item_doc_delete
AFTER DELETE ON item_documento_recebimento
FOR EACH ROW
BEGIN
    DECLARE v_id_servico INT;
    DECLARE v_remuneracao FLOAT;
    
    SELECT s.id, s.remuneracao INTO v_id_servico, v_remuneracao
    FROM item_os io
    JOIN servico s ON io.id_servico = s.id
    WHERE io.id = OLD.id_item_os;

    UPDATE servico
    SET saldo_horas = saldo_horas + (OLD.quantidade_entregue * v_remuneracao)
    WHERE id = v_id_servico;
END //

DELIMITER ;
