# Implementação: Funções Python de Transformação por Usuário

## Resumo das Mudanças

Este documento descreve a implementação completa do sistema de associação de funções Python de transformação por usuário, substituindo o conteúdo fixo no código por um sistema baseado em banco de dados.

## Problema Resolvido

Anteriormente, o campo "Função Python de Transformação:" em `addConfig.php` e `updConfig.php` tinha as opções hardcoded (fixas) no código. Agora essas funções são recuperadas de uma tabela no banco de dados MySQL, permitindo:

1. **Administração centralizada**: Funções podem ser adicionadas/removidas sem alterar código
2. **Personalização por usuário**: Cada usuário tem suas funções configuradas no banco
3. **Sincronização automática**: Novos usuários recebem automaticamente as funções padrão

## Estrutura do Banco de Dados

### Tabela 1: `funcion_configuration`
Armazena as funções Python disponíveis no sistema.

```sql
CREATE TABLE `funcion_configuration` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(128) NOT NULL UNIQUE,
  `modulo_python` VARCHAR(255) NOT NULL UNIQUE,
  `descricao` TEXT NULL,
  `grupo` VARCHAR(64) NULL,
  `ordem` INT NOT NULL DEFAULT 0,
  `ativo` BOOLEAN NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_modulo_python` (`modulo_python`),
  KEY `idx_grupo` (`grupo`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB;
```

**Campos:**
- `id`: Identificador único
- `nome`: Nome amigável da função (exibido no formulário)
- `modulo_python`: Caminho do módulo Python (valor salvo no banco de dados)
- `descricao`: Descrição detalhada
- `grupo`: Agrupamento para exibição em `<optgroup>` (ex: "Recomendado", "Ingestão de Fontes")
- `ordem`: Ordem de exibição dentro do grupo
- `ativo`: Se a função está disponível para novos usuários
- `criado_em` e `atualizado_em`: Timestamps de auditoria

### Tabela 2: `user_funcion_configuration`
Associação muitos-para-muitos entre usuários e funções.

```sql
CREATE TABLE `user_funcion_configuration` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `funcion_configuration_id` INT UNSIGNED NOT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_funcion` (`usuario_id`, `funcion_configuration_id`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_funcion_id` (`funcion_configuration_id`),
  CONSTRAINT `fk_user_funcion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_funcion_funcion` FOREIGN KEY (`funcion_configuration_id`) REFERENCES `funcion_configuration` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
```

## Funções Padrão Inseridas

Sete funções padrão foram inseridas na tabela `funcion_configuration`:

1. **Pipeline Completo (Bronze + Silver + Gold)** - Grupo: Recomendado
   - Módulo: `lib.medallion_pipeline.raw_to_medallion`

2. **MySQL → Medallion** - Grupo: Ingestão de Fontes SQL
   - Módulo: `lib.mysql_ingestion.mysql_to_medallion`

3. **MySQL → Raw** - Grupo: Ingestão de Fontes SQL
   - Módulo: `lib.mysql_ingestion.ingest_mysql_to_raw`

4. **Bronze (Raw → Bronze CSV)** - Grupo: Camadas Individuais
   - Módulo: `lib.bronze_layer.raw_to_bronze`

5. **Silver (Bronze → Silver Parquet)** - Grupo: Camadas Individuais
   - Módulo: `lib.silver_layer.bronze_to_silver`

6. **Gold (Silver → Gold Parquet Otimizado)** - Grupo: Camadas Individuais
   - Módulo: `lib.gold_layer.silver_to_gold`

7. **Função Legada** - Grupo: Legado (desativada)
   - Módulo: `lib.minio_tasks.transform_data_with_pandas`

## Modelos Criados

### 1. `FuncionConfigurationModel.php`
Gerencia a tabela `funcion_configuration`.

**Métodos principais:**
- `getAllAtivas()`: Retorna todas as funções ativas
- `getFuncoesParaUsuario($usuarioId)`: Retorna funções de um usuário específico
- `getPorModuloPython($moduloPython)`: Busca uma função pelo módulo Python
- `getAgrupadasPorGrupo()`: Agrupa funções por grupo (para exibição em optgroup)
- `getAgrupadasPorGrupoParaUsuario($usuarioId)`: Agrupa funções do usuário por grupo

### 2. `UsuarioFuncionConfigurationModel.php`
Gerencia a tabela `user_funcion_configuration` e a sincronização.

**Métodos principais:**
- `temFuncao($usuarioId, $funcionConfigurationId)`: Verifica se usuário tem função
- `contarFuncoesDoUsuario($usuarioId)`: Conta quantas funções um usuário tem
- `getFuncoesDoUsuario($usuarioId)`: Retorna todas as funções do usuário
- `limparFuncoesDoUsuario($usuarioId)`: Remove todas as funções do usuário
- `adicionarFuncaoAoUsuario($usuarioId, $funcionConfigurationId)`: Adiciona uma função
- `sincronizarComPadrao($usuarioId)`: **Sincroniza com todas as funções padrão ativas**
- `getFuncoesFormatadas($usuarioId)`: Retorna funções formatadas para o select

## Views Modificadas

### 1. `addConfig.php` (Linha ~479)
Substituiu o select hardcoded por código dinâmico:

```php
<select name="python_module_path" id="python_module_path" required onchange="validatePipelineSelection()">
    <option value="">-- Selecione o tipo de pipeline --</option>
    <?php 
    $usuarioFuncionModel = new \App\Models\UsuarioFuncionConfigurationModel();
    $usuarioId = (int) \App\Helpers\SessionHelper::getUserId();
    $funcoesAgrupadas = $usuarioFuncionModel->getFuncoesFormatadas($usuarioId);
    
    foreach ($funcoesAgrupadas as $grupo => $funcoes): ?>
        <optgroup label="<?php echo htmlspecialchars($grupo); ?>">
            <?php foreach ($funcoes as $funcao): ?>
                <option value="<?php echo htmlspecialchars($funcao->modulo_python); ?>">
                    <?php echo htmlspecialchars($funcao->nome); ?>
                </option>
            <?php endforeach; ?>
        </optgroup>
    <?php endforeach; ?>
</select>
```

### 2. `updConfig.php` (Linha ~255)
Mesma modificação que `addConfig.php`, porém com atributo `selected` para persistência:

```php
<?php echo ($python_module_path === $funcao->modulo_python) ? 'selected' : ''; ?>
```

## Controladores Modificados

### 1. `UsuarioController.php`

#### Método `insert()` (Criação de usuário - Admin)
Adicionado código para sincronizar funções:

```php
// Sincronizar funções Python padrão do novo usuário
if ($idUsuario) {
    $syncResult = $usuarioFuncionModel->sincronizarComPadrao($idUsuario);
    if (!$syncResult) {
        log_message('warning', "Falha ao sincronizar funções padrão para novo usuário ID: {$idUsuario}");
    }
}
```

#### Método `insertSigIn()` (Cadastro de usuário - Self-signup)
Mesma sincronização adicionada.

#### Método `logar()` (Login com email/senha)
Adicionado código para garantir que usuários com login tenham funções:

```php
// Sincroniza funções Python do usuário (garante que tem as funções padrão)
try {
    $usuarioFuncionModel = new \App\Models\UsuarioFuncionConfigurationModel();
    $countFuncoes = $usuarioFuncionModel->contarFuncoesDoUsuario($usuario->id);
    
    if ($countFuncoes == 0) {
        // Se não tem funções configuradas, sincroniza com padrão
        $syncResult = $usuarioFuncionModel->sincronizarComPadrao($usuario->id);
        if ($syncResult) {
            log_message('info', "Funções Python sincronizadas para usuário no login: {$usuario->id}");
        }
    }
} catch (\Exception $e) {
    log_message('warning', "Erro ao sincronizar funções no login: " . $e->getMessage());
}
```

#### Método `logarUsuarioConfirmaEmail()` (Login após confirmação de email)
Mesma sincronização adicionada.

### 2. `AuthController.php`

#### Callback do Google Auth
Adicionado código para sincronizar funções quando usuário autentica com Google:

```php
// Sincroniza funções Python do usuário (garante que tem as funções padrão)
try {
    $usuarioFuncionModel = new \App\Models\UsuarioFuncionConfigurationModel();
    $countFuncoes = $usuarioFuncionModel->contarFuncoesDoUsuario($usuario->id);
    
    if ($countFuncoes == 0) {
        $syncResult = $usuarioFuncionModel->sincronizarComPadrao($usuario->id);
        if ($syncResult) {
            log_message('info', "Funções Python sincronizadas para novo usuário Google Auth: {$usuario->id}");
        }
    }
} catch (\Exception $e) {
    log_message('warning', "Erro ao sincronizar funções no Google Auth: " . $e->getMessage());
}
```

## Fluxo de Sincronização

1. **Novo usuário é criado** (via admin ou self-signup):
   - Sistema chama `sincronizarComPadrao($usuarioId)`
   - Todas as funções ativas de `funcion_configuration` são inseridas em `user_funcion_configuration`
   - Log: "Funções sincronizadas com sucesso para usuário {id}"

2. **Usuário faz login**:
   - Sistema verifica se usuário tem funções configuradas
   - Se não tem (`contarFuncoesDoUsuario == 0`):
     - Chama `sincronizarComPadrao()`
     - Garante que usuários legados terão funções
   - Se já tem: nenhuma ação (usa as funções existentes)

3. **Usuário acessa formulário de criação/edição de pipeline**:
   - Sistema carrega as funções do usuário de `user_funcion_configuration`
   - `getFuncoesFormatadas()` agrupa por grupo
   - Select HTML exibe opções agrupadas em `<optgroup>`

## Benefícios da Implementação

✅ **Flexibilidade**: Adicione/remova funções sem alterar código
✅ **Escalabilidade**: Suporte a múltiplas versões de funções
✅ **Auditoria**: Timestamps indicam quando funções foram associadas
✅ **Dados consistentes**: Foreign keys garantem integridade referencial
✅ **Sincronização automática**: Usuários legados ganham funções automaticamente
✅ **Falha silenciosa**: Erros de sincronização são logados mas não bloqueiam login

## Migração de Dados

A migração SQL `create_user_funcion_configuration.sql` cria as tabelas e insere as 7 funções padrão automaticamente.

Para usuários existentes, a sincronização ocorre automaticamente no primeiro login após a migração.

## Instruções de Uso

### Para adicionar uma nova função Python:

1. Insira um novo registro em `funcion_configuration`:
   ```sql
   INSERT INTO funcion_configuration (nome, modulo_python, descricao, grupo, ordem, ativo) 
   VALUES ('Minha Função', 'lib.meu_modulo.minha_funcao', 'Descrição', 'Meu Grupo', 8, 1);
   ```

2. Sincronize usuários existentes que precisam da função:
   ```sql
   INSERT INTO user_funcion_configuration (usuario_id, funcion_configuration_id)
   SELECT usuario.id, fc.id 
   FROM usuario, funcion_configuration fc 
   WHERE fc.nome = 'Minha Função';
   ```

3. Novos usuários receberão a função automaticamente na criação.

### Para desativar uma função:

```sql
UPDATE funcion_configuration SET ativo = 0 WHERE id = X;
```

Usuários existentes continuam tendo acesso (não é removida de `user_funcion_configuration`), mas novos usuários não receberão.

## Arquivos Alterados

### Delta (datalake-air-flow-delta):
- ✅ `/app/Database/Migrations/create_user_funcion_configuration.sql` (NOVO)
- ✅ `/app/Models/FuncionConfigurationModel.php` (NOVO)
- ✅ `/app/Models/UsuarioFuncionConfigurationModel.php` (NOVO)
- ✅ `/app/Views/addConfig.php`
- ✅ `/app/Views/updConfig.php`
- ✅ `/app/Controllers/UsuarioController.php`
- ✅ `/app/Controllers/AuthController.php`

### Teste (datalake-air-flow-teste):
- ✅ `/app/Database/Migrations/create_user_funcion_configuration.sql` (NOVO)
- ✅ `/app/Models/FuncionConfigurationModel.php` (NOVO)
- ✅ `/app/Models/UsuarioFuncionConfigurationModel.php` (NOVO)
- ✅ `/app/Views/addConfig.php`
- ✅ `/app/Views/updConfig.php`
- ✅ `/app/Controllers/UsuarioController.php`
- ✅ `/app/Controllers/AuthController.php`

## Próximos Passos

1. **Executar migração**: Execute o script SQL para criar as tabelas
2. **Testar**: Crie um novo usuário e verifique se as funções aparecem no formulário
3. **Verificar logs**: Verifique `writable/logs/` para mensagens de sincronização
4. **Sincronizar usuários legados**: Se desejar, force sincronização de usuários antigos
   
```php
// Script para sincronizar todos os usuários existentes
$usuarioModel = new UsuarioModel();
$usuarios = $usuarioModel->findAll();
$usuarioFuncionModel = new UsuarioFuncionConfigurationModel();

foreach ($usuarios as $usuario) {
    $usuarioFuncionModel->sincronizarComPadrao($usuario->id);
}
```

---

**Data de Implementação**: 23 de Janeiro de 2026
**Versão**: 1.0
**Status**: ✅ Completo em ambos os workspaces
