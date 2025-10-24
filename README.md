# Poll System (Drupal 10)
Este projeto entrega um sistema de votação (perguntas/opções, votos únicos por usuário, API própria com login/token, rate limit e cache).

## Requisitos
- Lando instalado.
- Docker em funcionamento.
- (Opcional) Postman para testes da API.


## Instalação com dump
1. mkdir sistema-votacao
2. cd sistema-votacao
3. git clone https://github.com/eduardopbitencourt26-eng/teste-nttdata.git .
4. lando start
5. lando composer install
6. cp web/sites/default/default.settings.php web/sites/default/settings.php
7. chmod 644 web/sites/default/settings.php
8. Gerar um novo base64: `lando drush php-eval "echo \Drupal\Component\Utility\Crypt::randomBytesBase64(55) . PHP_EOL;"` (Copie)
9. Em `settings.php` procure por `$settings['hash_salt']` e cole o novo hash.
10. Adicione também ao fim do arquivo `settings.php` a seguinte configuração de database e sync:
    ```php
    $databases['default']['default'] = array (
        'database' => 'drupal10',
        'username' => 'drupal10',
        'password' => 'drupal10',
        'prefix' => '',
        'host' => 'database',
        'port' => '3306',
        'isolation_level' => 'READ COMMITTED',
        'driver' => 'mysql',
        'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
        'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
    );
    $settings['config_sync_directory'] = '../config/sync';
    ```
11. lando db-import web/modules/custom/poll_system/dump/poll_system_dump.sql
12. lando drush cr
13. O módulo poll_system é para ser ativado junto com a importação do database, caso não seja habilitado, ative usando: `lando drush en poll_system -y`.
14. Conceda Permissões (caso ainda não configurados):
    **Access Poll System API (Read)** => Anonymous User,
    **Administer Poll System** => Administrator, 
    **Vote on polls** => Authenticated User, 
    **Access Poll API** => Authenticated User.
15. Configure: `/admin/config/system/poll-system` (habilitar votação, chave de API etc.).

## Se você ainda não tem um usuário administrativo
- Crie o usuário:: `lando drush ucrt admin --mail="admin@example.com" --password="admin"`
- Dê a permissão:: `lando drush user:role:add administrator admin`

## API Key
A API exige X-API-Key (config interna) e Bearer Token para votar.
- `lando drush cset poll_system.settings api_key 'minha-api-key' -y`
- `lando drush cr`

## Admin
- Lista de perguntas cadastradas: `/admin/content/polls`
- Lista de perguntas + quantidade de opções: `/admin/content/polls/options`
- Lista de opções cadastradas na pergunta: `/admin/content/polls/{ID}/options`

## UI CMS
- Página de listagem de perguntas abertas: `/polls`
- Página de votação em pergunta específica (autenticar para votar): `/poll/{id}`

## API
Envie `X-API-Key` se configurado(`/admin/config/system/poll-system`), para todos os endpoints da API.

- `POST /api/login` — body `{"username": "{{USERNAME}}", "password": "{{PASSWORD}}" }`
- `GET /api/poll/questions` — params `?page={{page}}&per_page={{per_page}}`
- `GET /api/poll/questions/{id}` — params `?page={{page}}&per_page={{per_page}}`
- `POST /api/poll/questions/{id}/vote` — body `{ "option_id": <int> }`
- `GET /api/poll/questions/{id}/results` — params `?page={{page}}&per_page={{per_page}}`
- `POST /api/logout` 

## Concorrência e integridade
- Votos gravados dentro de transação. 
- Verificação direta no DB por `(question, uid)` evita votos duplicados.
- Paginação no CMS e API.
- Cache de resultados.


## Observabilidade
- Canal de log: `logger.channel.poll_system` (erros e eventos de voto).


## Postman
Coleção em `poll_system/postman/PollSystem.postman_collection.json`. Importe a collection no Postman e preencha as variáveis se necessário.

## Segurança
- API Key para requisição de quaisquer endpoint.
- Access Token Bearer para permitir voto por usuário logado (API).
- Permissões do móduulo dedicadas.
