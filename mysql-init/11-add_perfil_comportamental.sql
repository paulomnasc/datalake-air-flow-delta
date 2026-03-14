DELIMITER $$

CREATE PROCEDURE AddPerfilComportamentalColumn()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'usuario' 
        AND COLUMN_NAME = 'perfil_comportamental'
    ) THEN
        ALTER TABLE usuario
        ADD COLUMN perfil_comportamental VARCHAR(50) DEFAULT 'Em Evolução'
        COMMENT 'Armazena a categoria comportamental do aluno baseada no seu XP e engajamento (ex: Pragmático, Oportunista, Power User, Zumbi)';
    END IF;
END $$

DELIMITER ;

CALL AddPerfilComportamentalColumn();
DROP PROCEDURE AddPerfilComportamentalColumn;
