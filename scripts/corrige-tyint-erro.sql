/*
Esse script corrige o erro de chave estrangeira (tyint) nas tabelas relacionadas ao usuário. Ele remove as restrições de chave estrangeira existentes, modifica os tipos de dados para INT UNSIGNED e, em seguida, adiciona as restrições de chave estrangeira novamente com as ações de exclusão apropriadas.

Erro: Tabela usuario travou o autoincrement no valor 255

Motivo: as tabelas do banco foram criadas erroneamentes com o tipo de dados TINYINT para fk id ta tabela usuario e nas chaves estrangeiras, 
o que causava erros ao tentar inserir ou atualizar registros relacionados ao usuário. Este script corrige esse problema, 
garantindo que as chaves estrangeiras sejam do tipo INT UNSIGNED, permitindo a correta associação entre as tabelas e evitando erros futuros.  
*/

SHOW TABLE STATUS LIKE 'usuario';

ALTER TABLE course DROP CONSTRAINT fk_course_created_by;
ALTER TABLE funcion_configuration DROP CONSTRAINT fk_funcion_owner;
ALTER TABLE pasta DROP CONSTRAINT pasta_usuario_FK;
ALTER TABLE uc_definition DROP CONSTRAINT fk_uc_definition_created_by;
ALTER TABLE user_funcion_configuration DROP CONSTRAINT fk_user_funcion_usuario;
ALTER TABLE usuario_perfil DROP CONSTRAINT fk_usuario_perfil_usuario;
ALTER TABLE video DROP CONSTRAINT fk_video_created_by;
ALTER TABLE uc_progress DROP CONSTRAINT fk_uc_progress_definition;



-- FOREIGN KEY (created_by) REFERENCES usuario(id) ON DELETE SET NULL;

ALTER TABLE usuario MODIFY id INT UNSIGNED AUTO_INCREMENT;

DESC course;
DESC funcion_configuration;
ALTER TABLE funcion_configuration MODIFY owner_user_id INT UNSIGNED;

DESC pasta;
ALTER TABLE pasta MODIFY id_usuario INT UNSIGNED;



DESC uc_definition;
ALTER TABLE uc_definition MODIFY id INT UNSIGNED;
ALTER TABLE uc_definition MODIFY created_by INT UNSIGNED;

DESC video_progress;
ALTER TABLE video_progress MODIFY last_position_seconds INT UNSIGNED;

DESC course;
ALTER TABLE course MODIFY created_by INT UNSIGNED;

ALTER TABLE course ADD CONSTRAINT fk_course_created_by 
FOREIGN KEY (created_by) REFERENCES usuario(id) ON DELETE SET NULL;

 ALTER TABLE funcion_configuration ADD CONSTRAINT fk_funcion_owner
    FOREIGN KEY (owner_user_id) 
    REFERENCES usuario (id) 
    ON DELETE CASCADE;
 
 ALTER TABLE pasta ADD CONSTRAINT pasta_usuario_FK 
FOREIGN KEY (id_usuario) REFERENCES usuario(id) ON DELETE SET NULL;

ALTER TABLE uc_definition 
-- ADD CONSTRAINT fk_uc_definition_video FOREIGN KEY (video_id) 
--        REFERENCES video(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_uc_definition_created_by FOREIGN KEY (created_by) 
        REFERENCES usuario(id) ON DELETE SET NULL;

DESC user_funcion_configuration;
ALTER TABLE user_funcion_configuration MODIFY usuario_id INT UNSIGNED;
ALTER TABLE user_funcion_configuration ADD CONSTRAINT fk_user_funcion_usuario 
FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE;

DESC usuario_perfil;
ALTER TABLE usuario_perfil MODIFY id_usuario INT UNSIGNED;
ALTER TABLE usuario_perfil ADD CONSTRAINT fk_usuario_perfil_usuario 
FOREIGN KEY (id_usuario) REFERENCES usuario (id) ON DELETE CASCADE ON UPDATE CASCADE;

DESC video;
ALTER TABLE video MODIFY created_by INT UNSIGNED;
ALTER TABLE video ADD CONSTRAINT fk_video_created_by 
FOREIGN KEY (created_by) REFERENCES usuario(id) ON DELETE SET NULL;

DESC uc_progress;
ALTER TABLE uc_progress MODIFY uc_definition_id INT UNSIGNED;
ALTER TABLE uc_progress ADD CONSTRAINT fk_uc_progress_definition 
FOREIGN KEY (uc_definition_id) REFERENCES uc_definition(id) ON DELETE CASCADE;

	