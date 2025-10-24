# Poll System (Drupal 10)
Este projeto entrega um sistema de votação (perguntas/opções, votos únicos por usuário, API própria com login/token, rate limit e cache).

## Requisitos
- Lando instalado.
- Docker em funcionamento.
- (Opcional) Postman para testes da API.


## Instalação com dump
1. git clone <URL-do-repo> sistema-votacao
2. cd sistema-votacao
3. lando start
4. lando composer install
5. lando db-import caminho/para/o/dump.sql.gz
6. lando drush cr
4. Habilite: `lando drush en poll_system -y`.
5. Conceda Permissões:
    **Access Poll System API (Read)** => Anonymous User,
    **Administer Poll System** => Administrator, 
    **Vote on polls** => Authenticated User, 
    **Access Poll API** => Authenticated User.
6. Configure: `/admin/config/system/poll-system` (habilitar votação, chave de API etc.).

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
