USE fiscal;
SET FOREIGN_KEY_CHECKS=0;
ALTER TABLE usuario MODIFY id INT AUTO_INCREMENT;
ALTER TABLE usuario_perfil MODIFY id_usuario INT;
-- we will drop the OS tables anyway so we can recreate them with INT
DROP TABLE IF EXISTS os_item_contrato, os_item_os, os_status_recebimento, usuario_os, usuario_recebimento, avaliacao_qualidade_sla, documento_recebimento, ordem_servico, item_os, servico, atividade_macro, area_atuacao, catalogo_servicos, item_contrato, status_recebimento, status, tipo_documento;
SET FOREIGN_KEY_CHECKS=1;
