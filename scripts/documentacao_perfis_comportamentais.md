# Documentação da Estratégia de Perfis Comportamentais

Esta documentação detalha a implementação do sistema que categoriza os alunos em diferentes perfis comportamentais com base em seu engajamento dentro da plataforma.

## Objetivo
O objetivo central é identificar como os usuários interagem com os fluxos e o material didático, especialmente os usuários identificados como **"Oportunistas"**. Com isso em mãos, a equipe pedagógica/marketing consegue atuar com ações diretas ou mudar a etapa inicial para engajar esses perfis (forçando-os a experimentar um gatilho de ativação, como subir algo no S3).

---

## Os 4 Perfis Comportamentais

1. **Pragmático (Quer o Lab)**
   - **Regra:** `XP Obtido > 300` E `Progresso Vídeos < 10%`
   - **Comportamento:** É o cara que já sabe o que quer. Pula as aulas teóricas, suja a mão de graxa direto no terminal e quer ver o lab rodar.
   - **Valor:** Entrega rápida, aprende testando.

2. **Oportunista (Pulou o S3 p/ ver preço)**
   - **Regra:** `XP Obtido == 0` E `Última URI contém "subscription"`
   - **Comportamento:** Bateu o olho no LinkedIn, gostou da ideia e logo de cara clicou na página de assinatura para saber o valor de colocar o produto *on premise*, **antes de usar**.
   - **Problema:** Qualquer valor parece caro, pois a pessoa não sentiu o "Aha! moment" (ex: pipeline rodando no Airflow).
   - **Ação:** Remodelar o início do funil para o aluno fazer um pequeno *lab* (ganhar >0 XP) obrigatoriamente, antes do *pitch* de vendas.

3. **Power User**
   - **Regra:** `XP Obtido > 500` E `Progresso Vídeos > 50%`
   - **Comportamento:** O aluno dos sonhos. Assiste as aulas, consome o portal, faz todos os laboratórios. Tem alto valor de retenção a longo prazo.

4. **Zumbi**
   - **Regra:** `XP Obtido < 50` E `Progresso Vídeos == 0%`
   - **Comportamento:** Criou a conta e nunca mais logou/interagiu. Provavelmente esqueceu a senha ou perdeu o interesse logo no primeiro segundo.

5. **Em Evolução**
   - **Regra:** *Fallback* de todos os outros perfis. Quem não cai nas restrições acima, assume esse status (exemplo: novos alunos ainda acompanhando aulas, XP mediano, etc.).

---

## O Fluxo Técnico (Como funciona)

Foram construídas duas frentes tecnológicas: **A Nova Coluna no Banco de Dados** e **o Script de Extração/Atualização via Python**.

### 1. Preparação do Banco de Dados (Onde fica os dados?)

Para acomodar essa nova lógica, foi criado um script no diretório de inicialização do Docker (`mysql-init/11-add_perfil_comportamental.sql`).
Este script faz a adição da nova propriedade ao nosso banco:

```sql
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
```

Isso garante que sempre que subirmos esse banco, mesmo num novo deploy, a estrutura da tabela `usuario` comporte esse atributo.

### 2. O Script `categorizar_alunos.py`

Criamos o arquivo `scripts/categorizar_alunos.py`. Ele atua como um motor que analisa a base viva. Suas principais funções:

- **Autonomia em Conexão:** Lê inteligentemente o seu `.env` que está na raiz do frontend e levanta a conexão no banco (`dev` mapeando localmente para `127.0.0.1`, `prod` batendo direto no respectivo host).
- **Extração com JOINs Inteligentes:** Ele executa uma *super query* que varre a tabela de Progresso, somando as tarefas em XP (`uc_progress` + `uc_definition`), o `%` de vídeos em `video_progress` e a tabela de navegação `activity_logs`.
- **Ação:** O script lê esses dados numa tabela *pandas*, passa na peneira (as condicionais IFS vistas acima), printa no terminal para acompanhamento, e além disso, **salva um CSV**.
- **Devolvendo ao Banco:** Quando chamado com a flag `--update-db`, ele faz o processo reverso para injetar no MySQL (`UPDATE usuario SET perfil_comportamental...`) os valores atualizados.

---

## Como Operar o Motor? (Instruções Práticas)

Antes de rodar, ative seu ambiente virtual Python dentro da pasta raiz e instale as dependências necessárias no seu `venv` caso ainda não as tenha:

```bash
source venv/bin/activate
pip install pandas pymysql
```

Você estará trabalhando de dentro da pasta `scripts/`:

### Apenas Extrair Relatório (Gerar `.csv`)
No seu ambiente de desenvolvimento (`.env` local):
```bash
python scripts/categorizar_alunos.py --env dev
```

### Extrair E Gravar no Banco de Dados (Recomendado)
Para já preencher a coluna `perfil_comportamental` na tabela `usuario`:
```bash
python scripts/categorizar_alunos.py --env dev --update-db
```

### Produção?
Basta trocar o argumento dev por prod no terminal e o script tentará achar chaves como `database.prod.hostname`, mantendo o isolamento.
```bash
python scripts/categorizar_alunos.py --env prod --update-db
```

---

## Resultado Esperado

Ao final, se você for acessar seu banco:

```sql
SELECT nome, perfil_comportamental FROM usuario LIMIT 10;
```

A coluna **perfil_comportamental** não estará mais em branco ou `NULL`. Estará exibindo os nomes corretamente como **Oportunista (Pulou o S3 p/ ver preço)**, mostrando em tempo real o raio-x da sua audiência, pronta pra você engatilhar seu disparo de marketing.
