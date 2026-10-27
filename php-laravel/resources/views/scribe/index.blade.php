<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>API de Pacientes</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "{{ Request::root() }}";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-4.31.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-4.31.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-pacientes" class="tocify-header">
                <li class="tocify-item level-1" data-unique="pacientes">
                    <a href="#pacientes">Pacientes</a>
                </li>
                                    <ul id="tocify-subheader-pacientes" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="pacientes-GETapi-patients-cpf-availability">
                                <a href="#pacientes-GETapi-patients-cpf-availability">Verificar disponibilidade de CPF</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pacientes-GETapi-patients-export">
                                <a href="#pacientes-GETapi-patients-export">Exportar pacientes (CSV)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pacientes-GETapi-patients--patient_id--audits">
                                <a href="#pacientes-GETapi-patients--patient_id--audits">Histórico de alterações do paciente</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pacientes-POSTapi-patients--patient_id--restore">
                                <a href="#pacientes-POSTapi-patients--patient_id--restore">Restaurar paciente</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pacientes-GETapi-patients">
                                <a href="#pacientes-GETapi-patients">Listar pacientes</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pacientes-POSTapi-patients">
                                <a href="#pacientes-POSTapi-patients">Cadastrar paciente</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pacientes-GETapi-patients--id-">
                                <a href="#pacientes-GETapi-patients--id-">Detalhar paciente</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pacientes-PUTapi-patients--id-">
                                <a href="#pacientes-PUTapi-patients--id-">Atualizar paciente</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pacientes-DELETEapi-patients--id-">
                                <a href="#pacientes-DELETEapi-patients--id-">Excluir paciente</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-monitoramento" class="tocify-header">
                <li class="tocify-item level-1" data-unique="monitoramento">
                    <a href="#monitoramento">Monitoramento</a>
                </li>
                                    <ul id="tocify-subheader-monitoramento" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="monitoramento-GETapi-health">
                                <a href="#monitoramento-GETapi-health">Verificar saúde da API</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li></li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>API REST para cadastro, consulta, edição e exclusão (soft delete) de pacientes.</p>
<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>
<p>Esta documentação descreve a API REST do cadastro de pacientes.</p>
<p>Todas as respostas são JSON; envie o header <code>Accept: application/json</code>. CPF, RG e celular aceitam máscara
na entrada e são armazenados e retornados apenas com dígitos. Datas usam o formato <code>AAAA-MM-DD</code>.
A exclusão é lógica (soft delete): o paciente deixa de aparecer na listagem e passa a retornar 404.
CPF, RG e celular são armazenados criptografados no banco (a API sempre devolve os valores em claro); por isso
a busca por CPF exige o CPF completo. Cada alteração de paciente fica registrada no histórico (<code>/patients/{id}/audits</code>).</p>
<aside>Os exemplos de requisição (bash e JavaScript) ficam na área escura à direita. Use o botão
"Try it out" de cada endpoint para enviar uma requisição real à API.</aside>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>This API is not authenticated.</p>

        <h1 id="pacientes">Pacientes</h1>

    <p>Cadastro de pacientes: listagem com busca, filtros, ordenação e paginação, exportação em CSV, detalhe,
criação, atualização, exclusão lógica (lixeira), restauração, histórico de alterações e verificação de
disponibilidade de CPF.</p>

                                <h2 id="pacientes-GETapi-patients-cpf-availability">Verificar disponibilidade de CPF</h2>

<p>
</p>

<p>Informa se um CPF pode ser usado em um cadastro (não pertence a nenhum paciente ativo). Pacientes excluídos
não contam. Na edição, envie <code>ignore</code> com o ID do próprio paciente. CPF inválido retorna 422 com as mesmas
mensagens do cadastro.</p>

<span id="example-requests-GETapi-patients-cpf-availability">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/patients/cpf-availability?cpf=625.189.530-60&amp;ignore=1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/cpf-availability"
);

const params = {
    "cpf": "625.189.530-60",
    "ignore": "1",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-patients-cpf-availability">
            <blockquote>
            <p>Example response (200, CPF disponível):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;available&quot;: true
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, CPF em uso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;available&quot;: false,
    &quot;message&quot;: &quot;J&aacute; existe um paciente cadastrado com este CPF.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, CPF inválido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O CPF informado &eacute; inv&aacute;lido.&quot;,
    &quot;errors&quot;: {
        &quot;cpf&quot;: [
            &quot;O CPF informado &eacute; inv&aacute;lido.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-patients-cpf-availability" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-patients-cpf-availability"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-patients-cpf-availability"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-patients-cpf-availability" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-patients-cpf-availability">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-patients-cpf-availability" data-method="GET"
      data-path="api/patients/cpf-availability"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-patients-cpf-availability', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-patients-cpf-availability"
                    onclick="tryItOut('GETapi-patients-cpf-availability');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-patients-cpf-availability"
                    onclick="cancelTryOut('GETapi-patients-cpf-availability');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-patients-cpf-availability"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/patients/cpf-availability</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-patients-cpf-availability"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-patients-cpf-availability"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>cpf</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cpf"                data-endpoint="GETapi-patients-cpf-availability"
               value="625.189.530-60"
               data-component="query">
    <br>
<p>CPF a verificar, com ou sem máscara. Deve ser válido (dígitos verificadores conferidos). Example: <code>625.189.530-60</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>ignore</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="ignore"                data-endpoint="GETapi-patients-cpf-availability"
               value="1"
               data-component="query">
    <br>
<p>ID de um paciente a desconsiderar (use na edição para não acusar o próprio CPF). Must be at least 1. Example: <code>1</code></p>
            </div>
                </form>

                    <h2 id="pacientes-GETapi-patients-export">Exportar pacientes (CSV)</h2>

<p>
</p>

<p>Baixa os pacientes em CSV, com os mesmos filtros e ordenação da listagem (<code>search</code>, <code>sex</code>, <code>age_min</code>,
<code>age_max</code>, <code>sort</code>, <code>direction</code>, <code>trashed</code>) e sem paginação. Arquivo UTF-8 com BOM, separador <code>;</code> e
quebra de linha CRLF (abre direto no Excel em pt-BR), nome <code>pacientes-AAAAMMDD-HHMM.csv</code> (horário de
Brasília). Valores formatados: CPF <code>000.000.000-00</code>, celular <code>(00) 00000-0000</code>, datas <code>DD/MM/AAAA</code>,
sexo <code>Masculino</code>/<code>Feminino</code>; com <code>trashed=only</code> há a coluna extra <code>Excluído em</code> (<code>DD/MM/AAAA HH:MM</code>).
Células que começam com <code>=</code>, <code>+</code>, <code>-</code>, <code>@</code>, tab ou CR recebem um <code>'</code> na frente (proteção contra
injeção de fórmulas). O &quot;Try it out&quot; mostra o CSV como texto; para baixar o arquivo, abra a URL no
navegador.</p>

<span id="example-requests-GETapi-patients-export">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/patients/export?search=maria&amp;sex=F&amp;age_min=18&amp;age_max=65&amp;sort=name&amp;direction=asc&amp;trashed=only" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/export"
);

const params = {
    "search": "maria",
    "sex": "F",
    "age_min": "18",
    "age_max": "65",
    "sort": "name",
    "direction": "asc",
    "trashed": "only",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-patients-export">
            <blockquote>
            <p>Example response (200, CSV (com BOM UTF-8)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">Nome;CPF;RG;Data de nascimento;Idade;Sexo;Celular;E-mail;Descri&ccedil;&atilde;o
Maria da Silva;625.189.530-60;35688019X;20/07/1996;30;Feminino;(11) 92853-5718;maria@example.com;&quot;Paciente com miopia leve; retorno anual.&quot;</code>
 </pre>
    </span>
<span id="execution-results-GETapi-patients-export" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-patients-export"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-patients-export"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-patients-export" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-patients-export">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-patients-export" data-method="GET"
      data-path="api/patients/export"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-patients-export', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-patients-export"
                    onclick="tryItOut('GETapi-patients-export');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-patients-export"
                    onclick="cancelTryOut('GETapi-patients-export');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-patients-export"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/patients/export</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-patients-export"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-patients-export"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>search</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="search"                data-endpoint="GETapi-patients-export"
               value="maria"
               data-component="query">
    <br>
<p>Nome parcial ou CPF completo (como na listagem). Example: <code>maria</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>sex</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="sex"                data-endpoint="GETapi-patients-export"
               value="F"
               data-component="query">
    <br>
<p><code>M</code> ou <code>F</code>. Example: <code>F</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>age_min</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="age_min"                data-endpoint="GETapi-patients-export"
               value="18"
               data-component="query">
    <br>
<p>Idade mínima (0 a 150). Example: <code>18</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>age_max</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="age_max"                data-endpoint="GETapi-patients-export"
               value="65"
               data-component="query">
    <br>
<p>Idade máxima (0 a 150). Example: <code>65</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>sort</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="sort"                data-endpoint="GETapi-patients-export"
               value="name"
               data-component="query">
    <br>
<p><code>name</code>, <code>birth_date</code> ou <code>created_at</code>. Padrão: <code>name</code>. Example: <code>name</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>direction</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="direction"                data-endpoint="GETapi-patients-export"
               value="asc"
               data-component="query">
    <br>
<p><code>asc</code> ou <code>desc</code>. Padrão: <code>asc</code>. Example: <code>asc</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>trashed</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="trashed"                data-endpoint="GETapi-patients-export"
               value="only"
               data-component="query">
    <br>
<p><code>only</code> exporta apenas a lixeira (com a coluna <code>Excluído em</code>). Example: <code>only</code></p>
            </div>
                </form>

                    <h2 id="pacientes-GETapi-patients--patient_id--audits">Histórico de alterações do paciente</h2>

<p>
</p>

<p>Lista as alterações do paciente, da mais recente para a mais antiga (desempate por <code>id</code> decrescente),
paginadas como a listagem. Funciona também para pacientes na lixeira. <code>event</code> é <code>created</code>, <code>updated</code>,
<code>deleted</code> ou <code>restored</code>; <code>changes</code> traz <code>{campo: {old, new}}</code> com os valores crus (como na API): todos os
campos em <code>created</code>, só os alterados em <code>updated</code> e <code>{}</code> em <code>deleted</code>/<code>restored</code>. <code>user_id</code> é sempre
<code>null</code> enquanto a API não tem autenticação. Os valores ficam criptografados no banco.</p>

<span id="example-requests-GETapi-patients--patient_id--audits">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/patients/1/audits?page=1&amp;per_page=10" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/1/audits"
);

const params = {
    "page": "1",
    "per_page": "10",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-patients--patient_id--audits">
            <blockquote>
            <p>Example response (200, Sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 2,
            &quot;event&quot;: &quot;updated&quot;,
            &quot;changes&quot;: {
                &quot;name&quot;: {
                    &quot;old&quot;: &quot;Maria da Silva&quot;,
                    &quot;new&quot;: &quot;Maria da Silva Lima&quot;
                },
                &quot;phone&quot;: {
                    &quot;old&quot;: &quot;11928535718&quot;,
                    &quot;new&quot;: &quot;11911111111&quot;
                }
            },
            &quot;ip&quot;: &quot;172.18.0.1&quot;,
            &quot;user_agent&quot;: &quot;Mozilla/5.0 (X11; Linux x86_64)&quot;,
            &quot;user_id&quot;: null,
            &quot;created_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;
        },
        {
            &quot;id&quot;: 1,
            &quot;event&quot;: &quot;created&quot;,
            &quot;changes&quot;: {
                &quot;name&quot;: {
                    &quot;old&quot;: null,
                    &quot;new&quot;: &quot;Maria da Silva&quot;
                },
                &quot;cpf&quot;: {
                    &quot;old&quot;: null,
                    &quot;new&quot;: &quot;62518953060&quot;
                },
                &quot;rg&quot;: {
                    &quot;old&quot;: null,
                    &quot;new&quot;: &quot;35688019X&quot;
                },
                &quot;birth_date&quot;: {
                    &quot;old&quot;: null,
                    &quot;new&quot;: &quot;1996-07-20&quot;
                },
                &quot;sex&quot;: {
                    &quot;old&quot;: null,
                    &quot;new&quot;: &quot;F&quot;
                },
                &quot;phone&quot;: {
                    &quot;old&quot;: null,
                    &quot;new&quot;: &quot;11928535718&quot;
                },
                &quot;email&quot;: {
                    &quot;old&quot;: null,
                    &quot;new&quot;: &quot;maria@example.com&quot;
                },
                &quot;description&quot;: {
                    &quot;old&quot;: null,
                    &quot;new&quot;: null
                }
            },
            &quot;ip&quot;: &quot;172.18.0.1&quot;,
            &quot;user_agent&quot;: &quot;Mozilla/5.0 (X11; Linux x86_64)&quot;,
            &quot;user_id&quot;: null,
            &quot;created_at&quot;: &quot;2026-10-26T15:30:00-03:00&quot;
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;http://localhost:8000/api/patients/1/audits?page=1&quot;,
        &quot;last&quot;: &quot;http://localhost:8000/api/patients/1/audits?page=1&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;from&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;links&quot;: [
            {
                &quot;url&quot;: null,
                &quot;label&quot;: &quot;&amp;laquo; Previous&quot;,
                &quot;active&quot;: false
            },
            {
                &quot;url&quot;: &quot;http://localhost:8000/api/patients/1/audits?page=1&quot;,
                &quot;label&quot;: &quot;1&quot;,
                &quot;active&quot;: true
            },
            {
                &quot;url&quot;: null,
                &quot;label&quot;: &quot;Next &amp;raquo;&quot;,
                &quot;active&quot;: false
            }
        ],
        &quot;path&quot;: &quot;http://localhost:8000/api/patients/1/audits&quot;,
        &quot;per_page&quot;: 10,
        &quot;to&quot;: 2,
        &quot;total&quot;: 2
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Paciente não encontrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Paciente n&atilde;o encontrado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-patients--patient_id--audits" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-patients--patient_id--audits"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-patients--patient_id--audits"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-patients--patient_id--audits" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-patients--patient_id--audits">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-patients--patient_id--audits" data-method="GET"
      data-path="api/patients/{patient_id}/audits"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-patients--patient_id--audits', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-patients--patient_id--audits"
                    onclick="tryItOut('GETapi-patients--patient_id--audits');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-patients--patient_id--audits"
                    onclick="cancelTryOut('GETapi-patients--patient_id--audits');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-patients--patient_id--audits"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/patients/{patient_id}/audits</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-patients--patient_id--audits"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-patients--patient_id--audits"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>patient_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="patient_id"                data-endpoint="GETapi-patients--patient_id--audits"
               value="1"
               data-component="url">
    <br>
<p>ID do paciente (ativo ou na lixeira). Example: <code>1</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-patients--patient_id--audits"
               value="1"
               data-component="query">
    <br>
<p>Página desejada. Padrão: 1. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-patients--patient_id--audits"
               value="10"
               data-component="query">
    <br>
<p>Itens por página. Padrão: 10; máximo: 100. Example: <code>10</code></p>
            </div>
                </form>

                    <h2 id="pacientes-POSTapi-patients--patient_id--restore">Restaurar paciente</h2>

<p>
</p>

<p>Restaura um paciente da lixeira (desfaz a exclusão lógica). Falha com 409 se outro paciente ativo
estiver usando o mesmo CPF (o CPF pode ter sido reutilizado depois da exclusão).</p>

<span id="example-requests-POSTapi-patients--patient_id--restore">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/patients/1/restore" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/1/restore"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-patients--patient_id--restore">
            <blockquote>
            <p>Example response (200, Paciente restaurado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;Maria da Silva&quot;,
        &quot;cpf&quot;: &quot;62518953060&quot;,
        &quot;rg&quot;: &quot;35688019X&quot;,
        &quot;birth_date&quot;: &quot;1996-07-20&quot;,
        &quot;sex&quot;: &quot;F&quot;,
        &quot;phone&quot;: &quot;11928535718&quot;,
        &quot;email&quot;: &quot;maria@example.com&quot;,
        &quot;description&quot;: &quot;Paciente com miopia leve, retorno anual.&quot;,
        &quot;created_at&quot;: &quot;2026-10-26T15:30:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-10-26T15:45:00-03:00&quot;,
        &quot;age&quot;: 30,
        &quot;deleted_at&quot;: null
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Paciente não encontrado ou não excluído):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Paciente n&atilde;o encontrado.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (409, CPF em uso por outro paciente ativo):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;J&aacute; existe um paciente ativo com este CPF.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-patients--patient_id--restore" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-patients--patient_id--restore"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-patients--patient_id--restore"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-patients--patient_id--restore" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-patients--patient_id--restore">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-patients--patient_id--restore" data-method="POST"
      data-path="api/patients/{patient_id}/restore"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-patients--patient_id--restore', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-patients--patient_id--restore"
                    onclick="tryItOut('POSTapi-patients--patient_id--restore');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-patients--patient_id--restore"
                    onclick="cancelTryOut('POSTapi-patients--patient_id--restore');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-patients--patient_id--restore"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/patients/{patient_id}/restore</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-patients--patient_id--restore"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-patients--patient_id--restore"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>patient_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="patient_id"                data-endpoint="POSTapi-patients--patient_id--restore"
               value="1"
               data-component="url">
    <br>
<p>ID do paciente excluído. Example: <code>1</code></p>
            </div>
                    </form>

                    <h2 id="pacientes-GETapi-patients">Listar pacientes</h2>

<p>
</p>

<p>Retorna os pacientes ativos (não excluídos), com paginação no formato padrão do Laravel. Com <code>trashed=only</code>,
lista apenas os pacientes da lixeira (excluídos), com <code>deleted_at</code> preenchido; busca, ordenação e paginação
continuam valendo. Cada paciente traz <code>age</code> (idade em anos completos no fuso America/Sao_Paulo).
Os filtros (<code>search</code>, <code>sex</code>, <code>age_min</code>, <code>age_max</code>) se combinam entre si e com a lixeira; todos os
parâmetros são mantidos nos links de paginação.</p>

<span id="example-requests-GETapi-patients">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/patients?search=maria&amp;sex=F&amp;age_min=18&amp;age_max=65&amp;page=1&amp;per_page=10&amp;sort=name&amp;direction=asc&amp;trashed=only" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients"
);

const params = {
    "search": "maria",
    "sex": "F",
    "age_min": "18",
    "age_max": "65",
    "page": "1",
    "per_page": "10",
    "sort": "name",
    "direction": "asc",
    "trashed": "only",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-patients">
            <blockquote>
            <p>Example response (200, Sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;name&quot;: &quot;Maria da Silva&quot;,
            &quot;cpf&quot;: &quot;62518953060&quot;,
            &quot;rg&quot;: &quot;35688019X&quot;,
            &quot;birth_date&quot;: &quot;1996-07-20&quot;,
            &quot;sex&quot;: &quot;F&quot;,
            &quot;phone&quot;: &quot;11928535718&quot;,
            &quot;email&quot;: &quot;maria@example.com&quot;,
            &quot;description&quot;: &quot;Paciente com miopia leve, retorno anual.&quot;,
            &quot;created_at&quot;: &quot;2026-10-26T15:30:00-03:00&quot;,
            &quot;updated_at&quot;: &quot;2026-10-26T15:30:00-03:00&quot;,
            &quot;age&quot;: 30,
            &quot;deleted_at&quot;: null
        },
        {
            &quot;id&quot;: 2,
            &quot;name&quot;: &quot;Jo&atilde;o Pereira Santos&quot;,
            &quot;cpf&quot;: &quot;52998224725&quot;,
            &quot;rg&quot;: &quot;123456789&quot;,
            &quot;birth_date&quot;: &quot;1980-03-15&quot;,
            &quot;sex&quot;: &quot;M&quot;,
            &quot;phone&quot;: &quot;1133334444&quot;,
            &quot;email&quot;: null,
            &quot;description&quot;: null,
            &quot;created_at&quot;: &quot;2026-10-26T15:31:00-03:00&quot;,
            &quot;updated_at&quot;: &quot;2026-10-26T15:31:00-03:00&quot;,
            &quot;age&quot;: 46,
            &quot;deleted_at&quot;: null
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;http://localhost:8000/api/patients?page=1&quot;,
        &quot;last&quot;: &quot;http://localhost:8000/api/patients?page=1&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;from&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;links&quot;: [
            {
                &quot;url&quot;: null,
                &quot;label&quot;: &quot;&amp;laquo; Previous&quot;,
                &quot;active&quot;: false
            },
            {
                &quot;url&quot;: &quot;http://localhost:8000/api/patients?page=1&quot;,
                &quot;label&quot;: &quot;1&quot;,
                &quot;active&quot;: true
            },
            {
                &quot;url&quot;: null,
                &quot;label&quot;: &quot;Next &amp;raquo;&quot;,
                &quot;active&quot;: false
            }
        ],
        &quot;path&quot;: &quot;http://localhost:8000/api/patients&quot;,
        &quot;per_page&quot;: 10,
        &quot;to&quot;: 2,
        &quot;total&quot;: 2
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-patients" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-patients"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-patients"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-patients" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-patients">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-patients" data-method="GET"
      data-path="api/patients"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-patients', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-patients"
                    onclick="tryItOut('GETapi-patients');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-patients"
                    onclick="cancelTryOut('GETapi-patients');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-patients"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/patients</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-patients"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-patients"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>search</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="search"                data-endpoint="GETapi-patients"
               value="maria"
               data-component="query">
    <br>
<p>Busca por parte do nome, ou pelo CPF <strong>completo</strong> (11 dígitos, com ou sem máscara). CPF parcial não é buscável: o CPF é armazenado criptografado. Example: <code>maria</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>sex</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="sex"                data-endpoint="GETapi-patients"
               value="F"
               data-component="query">
    <br>
<p>Filtra por sexo: <code>M</code> ou <code>F</code> (outro valor é ignorado). Example: <code>F</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>age_min</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="age_min"                data-endpoint="GETapi-patients"
               value="18"
               data-component="query">
    <br>
<p>Idade mínima em anos completos (0 a 150; valor inválido é ignorado). Example: <code>18</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>age_max</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="age_max"                data-endpoint="GETapi-patients"
               value="65"
               data-component="query">
    <br>
<p>Idade máxima em anos completos (0 a 150; valor inválido é ignorado). Example: <code>65</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-patients"
               value="1"
               data-component="query">
    <br>
<p>Página desejada. Padrão: 1. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-patients"
               value="10"
               data-component="query">
    <br>
<p>Itens por página. Padrão: 10; máximo: 100 (valores maiores são limitados a 100). Example: <code>10</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>sort</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="sort"                data-endpoint="GETapi-patients"
               value="name"
               data-component="query">
    <br>
<p>Campo de ordenação: <code>name</code>, <code>birth_date</code> ou <code>created_at</code>. Padrão: <code>name</code> (valor fora da lista também usa <code>name</code>). Example: <code>name</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>direction</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="direction"                data-endpoint="GETapi-patients"
               value="asc"
               data-component="query">
    <br>
<p>Sentido da ordenação: <code>asc</code> ou <code>desc</code>. Padrão: <code>asc</code> (valor inválido também usa <code>asc</code>). O desempate é sempre por <code>id</code> crescente. Example: <code>asc</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>trashed</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="trashed"                data-endpoint="GETapi-patients"
               value="only"
               data-component="query">
    <br>
<p>Use <code>only</code> para listar apenas os pacientes excluídos (lixeira). Example: <code>only</code></p>
            </div>
                </form>

                    <h2 id="pacientes-POSTapi-patients">Cadastrar paciente</h2>

<p>
</p>

<p>Cria um paciente. CPF, RG e celular podem ser enviados com máscara; são armazenados só com dígitos.
O CPF deve ser válido e único entre os pacientes ativos.</p>

<span id="example-requests-POSTapi-patients">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/patients" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Maria da Silva\",
    \"cpf\": \"625.189.530-60\",
    \"rg\": \"35.688.019-X\",
    \"birth_date\": \"1996-07-20\",
    \"sex\": \"F\",
    \"phone\": \"(11) 92853-5718\",
    \"email\": \"maria@example.com\",
    \"description\": \"Paciente com miopia leve, retorno anual.\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Maria da Silva",
    "cpf": "625.189.530-60",
    "rg": "35.688.019-X",
    "birth_date": "1996-07-20",
    "sex": "F",
    "phone": "(11) 92853-5718",
    "email": "maria@example.com",
    "description": "Paciente com miopia leve, retorno anual."
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-patients">
            <blockquote>
            <p>Example response (201, Paciente criado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;Maria da Silva&quot;,
        &quot;cpf&quot;: &quot;62518953060&quot;,
        &quot;rg&quot;: &quot;35688019X&quot;,
        &quot;birth_date&quot;: &quot;1996-07-20&quot;,
        &quot;sex&quot;: &quot;F&quot;,
        &quot;phone&quot;: &quot;11928535718&quot;,
        &quot;email&quot;: &quot;maria@example.com&quot;,
        &quot;description&quot;: &quot;Paciente com miopia leve, retorno anual.&quot;,
        &quot;created_at&quot;: &quot;2026-10-26T15:30:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-10-26T15:30:00-03:00&quot;,
        &quot;age&quot;: 30,
        &quot;deleted_at&quot;: null
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Erro de validação):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O campo nome completo &eacute; obrigat&oacute;rio. (and 1 more error)&quot;,
    &quot;errors&quot;: {
        &quot;name&quot;: [
            &quot;O campo nome completo &eacute; obrigat&oacute;rio.&quot;
        ],
        &quot;cpf&quot;: [
            &quot;O CPF informado &eacute; inv&aacute;lido.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-patients" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-patients"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-patients"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-patients" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-patients">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-patients" data-method="POST"
      data-path="api/patients"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-patients', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-patients"
                    onclick="tryItOut('POSTapi-patients');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-patients"
                    onclick="cancelTryOut('POSTapi-patients');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-patients"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/patients</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-patients"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-patients"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-patients"
               value="Maria da Silva"
               data-component="body">
    <br>
<p>Nome completo do paciente (3 a 255 caracteres). Must be at least 3 characters. Must not be greater than 255 characters. Example: <code>Maria da Silva</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cpf</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cpf"                data-endpoint="POSTapi-patients"
               value="625.189.530-60"
               data-component="body">
    <br>
<p>CPF válido (dígitos verificadores conferidos), com ou sem máscara. Único entre os pacientes ativos. Armazenado só com dígitos. Example: <code>625.189.530-60</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>rg</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="rg"                data-endpoint="POSTapi-patients"
               value="35.688.019-X"
               data-component="body">
    <br>
<p>RG, com ou sem máscara (até 20 caracteres). Armazenado só com dígitos e a letra X. Must not be greater than 20 characters. Example: <code>35.688.019-X</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>birth_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="birth_date"                data-endpoint="POSTapi-patients"
               value="1996-07-20"
               data-component="body">
    <br>
<p>Data de nascimento no formato AAAA-MM-DD; não pode ser futura. Must be a valid date in the format <code>Y-m-d</code>. Must be a date before or equal to <code>today</code>. Example: <code>1996-07-20</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sex</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="sex"                data-endpoint="POSTapi-patients"
               value="F"
               data-component="body">
    <br>
<p>Sexo: <code>M</code> (Masculino) ou <code>F</code> (Feminino). Example: <code>F</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>M</code></li> <li><code>F</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-patients"
               value="(11) 92853-5718"
               data-component="body">
    <br>
<p>Celular com DDD, com ou sem máscara (10 ou 11 dígitos). Armazenado só com dígitos. Must be between 10 and 11 digits. Example: <code>(11) 92853-5718</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-patients"
               value="maria@example.com"
               data-component="body">
    <br>
<p>E-mail do paciente (opcional). Must be a valid email address. Must not be greater than 255 characters. Example: <code>maria@example.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="POSTapi-patients"
               value="Paciente com miopia leve, retorno anual."
               data-component="body">
    <br>
<p>Observações sobre o paciente (opcional, até 2000 caracteres). Must not be greater than 2000 characters. Example: <code>Paciente com miopia leve, retorno anual.</code></p>
        </div>
        </form>

                    <h2 id="pacientes-GETapi-patients--id-">Detalhar paciente</h2>

<p>
</p>



<span id="example-requests-GETapi-patients--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/patients/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/1"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-patients--id-">
            <blockquote>
            <p>Example response (200, Sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;Maria da Silva&quot;,
        &quot;cpf&quot;: &quot;62518953060&quot;,
        &quot;rg&quot;: &quot;35688019X&quot;,
        &quot;birth_date&quot;: &quot;1996-07-20&quot;,
        &quot;sex&quot;: &quot;F&quot;,
        &quot;phone&quot;: &quot;11928535718&quot;,
        &quot;email&quot;: &quot;maria@example.com&quot;,
        &quot;description&quot;: &quot;Paciente com miopia leve, retorno anual.&quot;,
        &quot;created_at&quot;: &quot;2026-10-26T15:30:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-10-26T15:30:00-03:00&quot;,
        &quot;age&quot;: 30,
        &quot;deleted_at&quot;: null
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Paciente não encontrado ou excluído):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Paciente n&atilde;o encontrado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-patients--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-patients--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-patients--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-patients--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-patients--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-patients--id-" data-method="GET"
      data-path="api/patients/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-patients--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-patients--id-"
                    onclick="tryItOut('GETapi-patients--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-patients--id-"
                    onclick="cancelTryOut('GETapi-patients--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-patients--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/patients/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-patients--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-patients--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="GETapi-patients--id-"
               value="1"
               data-component="url">
    <br>
<p>ID do paciente. Example: <code>1</code></p>
            </div>
                    </form>

                    <h2 id="pacientes-PUTapi-patients--id-">Atualizar paciente</h2>

<p>
</p>

<p>Substituição completa (PUT): envie todos os campos. Campos opcionais omitidos (<code>email</code>, <code>description</code>) são apagados.</p>

<span id="example-requests-PUTapi-patients--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/patients/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Maria da Silva\",
    \"cpf\": \"625.189.530-60\",
    \"rg\": \"35.688.019-X\",
    \"birth_date\": \"1996-07-20\",
    \"sex\": \"F\",
    \"phone\": \"(11) 92853-5718\",
    \"email\": \"maria@example.com\",
    \"description\": \"Paciente com miopia leve, retorno anual.\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/1"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Maria da Silva",
    "cpf": "625.189.530-60",
    "rg": "35.688.019-X",
    "birth_date": "1996-07-20",
    "sex": "F",
    "phone": "(11) 92853-5718",
    "email": "maria@example.com",
    "description": "Paciente com miopia leve, retorno anual."
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-patients--id-">
            <blockquote>
            <p>Example response (200, Paciente atualizado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;Maria da Silva&quot;,
        &quot;cpf&quot;: &quot;62518953060&quot;,
        &quot;rg&quot;: &quot;35688019X&quot;,
        &quot;birth_date&quot;: &quot;1996-07-20&quot;,
        &quot;sex&quot;: &quot;F&quot;,
        &quot;phone&quot;: &quot;11928535718&quot;,
        &quot;email&quot;: &quot;maria@example.com&quot;,
        &quot;description&quot;: &quot;Paciente com miopia leve, retorno anual.&quot;,
        &quot;created_at&quot;: &quot;2026-10-26T15:30:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-10-26T15:30:00-03:00&quot;,
        &quot;age&quot;: 30,
        &quot;deleted_at&quot;: null
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Paciente não encontrado ou excluído):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Paciente n&atilde;o encontrado.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Erro de validação):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O campo nome completo &eacute; obrigat&oacute;rio. (and 1 more error)&quot;,
    &quot;errors&quot;: {
        &quot;name&quot;: [
            &quot;O campo nome completo &eacute; obrigat&oacute;rio.&quot;
        ],
        &quot;cpf&quot;: [
            &quot;O CPF informado &eacute; inv&aacute;lido.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-patients--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-patients--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-patients--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-patients--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-patients--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-patients--id-" data-method="PUT"
      data-path="api/patients/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-patients--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-patients--id-"
                    onclick="tryItOut('PUTapi-patients--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-patients--id-"
                    onclick="cancelTryOut('PUTapi-patients--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-patients--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/patients/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/patients/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-patients--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-patients--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="PUTapi-patients--id-"
               value="1"
               data-component="url">
    <br>
<p>ID do paciente. Example: <code>1</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-patients--id-"
               value="Maria da Silva"
               data-component="body">
    <br>
<p>Nome completo do paciente (3 a 255 caracteres). Must be at least 3 characters. Must not be greater than 255 characters. Example: <code>Maria da Silva</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cpf</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cpf"                data-endpoint="PUTapi-patients--id-"
               value="625.189.530-60"
               data-component="body">
    <br>
<p>CPF válido (dígitos verificadores conferidos), com ou sem máscara. Único entre os pacientes ativos. Armazenado só com dígitos. Example: <code>625.189.530-60</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>rg</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="rg"                data-endpoint="PUTapi-patients--id-"
               value="35.688.019-X"
               data-component="body">
    <br>
<p>RG, com ou sem máscara (até 20 caracteres). Armazenado só com dígitos e a letra X. Must not be greater than 20 characters. Example: <code>35.688.019-X</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>birth_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="birth_date"                data-endpoint="PUTapi-patients--id-"
               value="1996-07-20"
               data-component="body">
    <br>
<p>Data de nascimento no formato AAAA-MM-DD; não pode ser futura. Must be a valid date in the format <code>Y-m-d</code>. Must be a date before or equal to <code>today</code>. Example: <code>1996-07-20</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sex</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="sex"                data-endpoint="PUTapi-patients--id-"
               value="F"
               data-component="body">
    <br>
<p>Sexo: <code>M</code> (Masculino) ou <code>F</code> (Feminino). Example: <code>F</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>M</code></li> <li><code>F</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="PUTapi-patients--id-"
               value="(11) 92853-5718"
               data-component="body">
    <br>
<p>Celular com DDD, com ou sem máscara (10 ou 11 dígitos). Armazenado só com dígitos. Must be between 10 and 11 digits. Example: <code>(11) 92853-5718</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="PUTapi-patients--id-"
               value="maria@example.com"
               data-component="body">
    <br>
<p>E-mail do paciente (opcional). Must be a valid email address. Must not be greater than 255 characters. Example: <code>maria@example.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="PUTapi-patients--id-"
               value="Paciente com miopia leve, retorno anual."
               data-component="body">
    <br>
<p>Observações sobre o paciente (opcional, até 2000 caracteres). Must not be greater than 2000 characters. Example: <code>Paciente com miopia leve, retorno anual.</code></p>
        </div>
        </form>

                    <h2 id="pacientes-DELETEapi-patients--id-">Excluir paciente</h2>

<p>
</p>

<p>Exclusão lógica (soft delete): o registro permanece no banco com <code>deleted_at</code> preenchido, deixa de aparecer
na listagem e passa a retornar 404. O CPF de um paciente excluído pode ser reutilizado em um novo cadastro.</p>

<span id="example-requests-DELETEapi-patients--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/patients/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/1"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-patients--id-">
            <blockquote>
            <p>Example response (204, Paciente excluído):</p>
        </blockquote>
                <pre>
<code>Empty response</code>
 </pre>
            <blockquote>
            <p>Example response (404, Paciente não encontrado ou excluído):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Paciente n&atilde;o encontrado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-patients--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-patients--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-patients--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-patients--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-patients--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-patients--id-" data-method="DELETE"
      data-path="api/patients/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-patients--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-patients--id-"
                    onclick="tryItOut('DELETEapi-patients--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-patients--id-"
                    onclick="cancelTryOut('DELETEapi-patients--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-patients--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/patients/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-patients--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-patients--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="DELETEapi-patients--id-"
               value="1"
               data-component="url">
    <br>
<p>ID do paciente. Example: <code>1</code></p>
            </div>
                    </form>

                <h1 id="monitoramento">Monitoramento</h1>

    

                                <h2 id="monitoramento-GETapi-health">Verificar saúde da API</h2>

<p>
</p>

<p>Usado por healthchecks de container e monitoramento. Verifica a conexão com o banco de dados.
Não sofre limite de requisições (throttle).</p>

<span id="example-requests-GETapi-health">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/health" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/health"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-health">
            <blockquote>
            <p>Example response (200, API e banco disponíveis):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;status&quot;: &quot;ok&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (503, Banco de dados indisponível):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;status&quot;: &quot;unavailable&quot;,
    &quot;message&quot;: &quot;Banco de dados indispon&iacute;vel.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-health" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-health"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-health"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-health" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-health">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-health" data-method="GET"
      data-path="api/health"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-health', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-health"
                    onclick="tryItOut('GETapi-health');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-health"
                    onclick="cancelTryOut('GETapi-health');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-health"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/health</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-health"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-health"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                            </div>
            </div>
</div>
</body>
</html>
