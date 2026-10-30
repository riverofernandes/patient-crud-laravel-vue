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
                                                                                <li class="tocify-item level-2" data-unique="pacientes-GETapi-patients--patient_id--photo">
                                <a href="#pacientes-GETapi-patients--patient_id--photo">Foto do paciente</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pacientes-POSTapi-patients--patient_id--photo">
                                <a href="#pacientes-POSTapi-patients--patient_id--photo">Enviar foto do paciente</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pacientes-DELETEapi-patients--patient_id--photo">
                                <a href="#pacientes-DELETEapi-patients--patient_id--photo">Remover foto do paciente</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-endereco" class="tocify-header">
                <li class="tocify-item level-1" data-unique="endereco">
                    <a href="#endereco">Endereço</a>
                </li>
                                    <ul id="tocify-subheader-endereco" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="endereco-GETapi-cep--code-">
                                <a href="#endereco-GETapi-cep--code-">Consultar CEP</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-atendimentos" class="tocify-header">
                <li class="tocify-item level-1" data-unique="atendimentos">
                    <a href="#atendimentos">Atendimentos</a>
                </li>
                                    <ul id="tocify-subheader-atendimentos" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="atendimentos-GETapi-patients--patient_id--consultations">
                                <a href="#atendimentos-GETapi-patients--patient_id--consultations">Listar atendimentos do paciente</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-POSTapi-patients--patient_id--consultations">
                                <a href="#atendimentos-POSTapi-patients--patient_id--consultations">Agendar atendimento</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-GETapi-patients--patient_id--timeline">
                                <a href="#atendimentos-GETapi-patients--patient_id--timeline">Linha do tempo do paciente</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-GETapi-consultations">
                                <a href="#atendimentos-GETapi-consultations">Agenda</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-GETapi-consultations--id-">
                                <a href="#atendimentos-GETapi-consultations--id-">Detalhar atendimento</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-PUTapi-consultations--id-">
                                <a href="#atendimentos-PUTapi-consultations--id-">Atualizar atendimento</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-PATCHapi-consultations--consultation_id--status">
                                <a href="#atendimentos-PATCHapi-consultations--consultation_id--status">Alterar status do atendimento</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-PUTapi-consultations--consultation_id--eye-exam">
                                <a href="#atendimentos-PUTapi-consultations--consultation_id--eye-exam">Registrar prontuário oftalmológico</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-GETapi-consultations--consultation_id--prescription">
                                <a href="#atendimentos-GETapi-consultations--consultation_id--prescription">Receita de óculos (PDF)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-GETapi-consultations--consultation_id--attachments">
                                <a href="#atendimentos-GETapi-consultations--consultation_id--attachments">Listar anexos do atendimento</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-POSTapi-consultations--consultation_id--attachments">
                                <a href="#atendimentos-POSTapi-consultations--consultation_id--attachments">Anexar exame ao atendimento</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-GETapi-attachments--attachment_id--download">
                                <a href="#atendimentos-GETapi-attachments--attachment_id--download">Baixar anexo</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="atendimentos-DELETEapi-attachments--id-">
                                <a href="#atendimentos-DELETEapi-attachments--id-">Excluir anexo</a>
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
<p>API REST para cadastro, consulta, edição e exclusão (soft delete) de pacientes (com endereço e foto), atendimentos, prontuário oftalmológico, receita de óculos e anexos de exames.</p>
<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>
<p>Esta documentação descreve a API REST do cadastro de pacientes.</p>
<p>Todas as respostas são JSON; envie o header <code>Accept: application/json</code>. CPF, RG e celular aceitam máscara
na entrada e são armazenados e retornados apenas com dígitos. Datas usam o formato <code>AAAA-MM-DD</code>.
A exclusão é lógica (soft delete): o paciente deixa de aparecer na listagem e passa a retornar 404.
CPF, RG e celular são armazenados criptografados no banco (a API sempre devolve os valores em claro); por isso
a busca por CPF exige o CPF completo. Cada alteração de paciente fica registrada no histórico (<code>/patients/{id}/audits</code>).</p>
<p>Atendimentos: datas e horas em ISO-8601, com fuso (<code>2026-11-03T14:30:00-03:00</code>) ou sem fuso (interpretado em
America/Sao_Paulo); as respostas sempre trazem o deslocamento de São Paulo. Os campos clínicos livres (<code>reason</code>,
<code>notes</code>, <code>diagnosis</code>, <code>observations</code>) são armazenados criptografados.
Endereço, foto e anexos: o paciente pode ter um endereço (<code>address</code>, logradouro/número/complemento/bairro
criptografados) e uma foto (<code>photo_url</code>); atendimentos podem ter anexos de exames (PDF, JPEG ou PNG). Fotos e anexos
ficam criptografados em disco privado. Uploads usam <code>multipart/form-data</code>. Toda resposta traz o header
<code>X-Request-Id</code> (o mesmo valor aparece na linha de log da requisição).</p>
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
sexo <code>Masculino</code>/<code>Feminino</code>, CEP <code>00000-000</code>, endereço &quot;rua, número - complemento - bairro&quot;; com <code>trashed=only</code> há a coluna extra <code>Excluído em</code> (<code>DD/MM/AAAA HH:MM</code>).
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

<code class="language-json" style="max-height: 300px;">Nome;CPF;RG;Data de nascimento;Idade;Sexo;Celular;E-mail;Descri&ccedil;&atilde;o;CEP;Endere&ccedil;o;Cidade;UF
Maria da Silva;625.189.530-60;35688019X;20/07/1996;30;Feminino;(11) 92853-5718;maria@example.com;&quot;Paciente com miopia leve; retorno anual.&quot;;01310-100;Avenida Paulista, 1578 - Sala 12 - Bela Vista;S&atilde;o Paulo;SP</code>
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
<p>Nome parcial ou CPF completo/parcial (como na listagem). Example: <code>maria</code></p>
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
campos em <code>created</code>, só os alterados em <code>updated</code> e <code>{}</code> em <code>deleted</code>/<code>restored</code>. O endereço aparece como
<code>address.&lt;campo&gt;</code> (ex.: <code>address.cep</code>) e a foto como <code>photo</code> (<code>{"old": null, "new": "adicionada" | "alterada" | "removida"}</code>, nunca a imagem). <code>user_id</code> é sempre
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
        &quot;deleted_at&quot;: null,
        &quot;address&quot;: {
            &quot;cep&quot;: &quot;01310100&quot;,
            &quot;street&quot;: &quot;Avenida Paulista&quot;,
            &quot;number&quot;: &quot;1578&quot;,
            &quot;complement&quot;: &quot;Sala 12&quot;,
            &quot;neighborhood&quot;: &quot;Bela Vista&quot;,
            &quot;city&quot;: &quot;S&atilde;o Paulo&quot;,
            &quot;state&quot;: &quot;SP&quot;
        },
        &quot;photo_url&quot;: &quot;http://localhost:8000/api/patients/1/photo?v=1&quot;
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
            &quot;deleted_at&quot;: null,
            &quot;address&quot;: {
                &quot;cep&quot;: &quot;01310100&quot;,
                &quot;street&quot;: &quot;Avenida Paulista&quot;,
                &quot;number&quot;: &quot;1578&quot;,
                &quot;complement&quot;: null,
                &quot;neighborhood&quot;: &quot;Bela Vista&quot;,
                &quot;city&quot;: &quot;S&atilde;o Paulo&quot;,
                &quot;state&quot;: &quot;SP&quot;
            },
            &quot;photo_url&quot;: null
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
            &quot;deleted_at&quot;: null,
            &quot;address&quot;: null,
            &quot;photo_url&quot;: null
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
<p>Busca por parte do nome, ou pelo CPF (com ou sem máscara): completo ou qualquer trecho de 3 ou mais dígitos. Example: <code>maria</code></p>
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
O CPF deve ser válido e único entre os pacientes ativos. <code>address</code> é opcional (objeto ou <code>null</code>); quando
enviado, todos os subcampos exceto <code>complement</code> são obrigatórios e os erros usam chaves com ponto
(<code>errors["address.cep"]</code>). A foto é enviada à parte (<code>POST /patients/{id}/photo</code>).</p>

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
    \"description\": \"Paciente com miopia leve, retorno anual.\",
    \"address\": {
        \"cep\": \"01310-100\",
        \"street\": \"Avenida Paulista\",
        \"number\": \"1578\",
        \"complement\": \"Sala 12\",
        \"neighborhood\": \"Bela Vista\",
        \"city\": \"São Paulo\",
        \"state\": \"SP\"
    }
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
    "description": "Paciente com miopia leve, retorno anual.",
    "address": {
        "cep": "01310-100",
        "street": "Avenida Paulista",
        "number": "1578",
        "complement": "Sala 12",
        "neighborhood": "Bela Vista",
        "city": "São Paulo",
        "state": "SP"
    }
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
        &quot;deleted_at&quot;: null,
        &quot;address&quot;: {
            &quot;cep&quot;: &quot;01310100&quot;,
            &quot;street&quot;: &quot;Avenida Paulista&quot;,
            &quot;number&quot;: &quot;1578&quot;,
            &quot;complement&quot;: &quot;Sala 12&quot;,
            &quot;neighborhood&quot;: &quot;Bela Vista&quot;,
            &quot;city&quot;: &quot;S&atilde;o Paulo&quot;,
            &quot;state&quot;: &quot;SP&quot;
        },
        &quot;photo_url&quot;: &quot;http://localhost:8000/api/patients/1/photo?v=1&quot;
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
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>address</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
<br>
<p>Endereço (opcional, objeto ou <code>null</code>). No PUT, omitido ou <code>null</code> remove o endereço; no PATCH, omitido mantém o endereço e <code>null</code> o remove. Quando enviado, os subcampos (exceto <code>complement</code>) são obrigatórios. Logradouro, número, complemento e bairro são armazenados criptografados.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>cep</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.cep"                data-endpoint="POSTapi-patients"
               value="01310-100"
               data-component="body">
    <br>
<p>CEP com 8 dígitos, com ou sem máscara (<code>00000-000</code>). Armazenado só com dígitos. Obrigatório quando <code>address</code> é enviado. Must be 8 digits. Example: <code>01310-100</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>street</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.street"                data-endpoint="POSTapi-patients"
               value="Avenida Paulista"
               data-component="body">
    <br>
<p>Logradouro (até 255). Obrigatório quando <code>address</code> é enviado. Must not be greater than 255 characters. Example: <code>Avenida Paulista</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>number</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.number"                data-endpoint="POSTapi-patients"
               value="1578"
               data-component="body">
    <br>
<p>Número (até 20; aceita &quot;S/N&quot;). Obrigatório quando <code>address</code> é enviado. Must not be greater than 20 characters. Example: <code>1578</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>complement</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.complement"                data-endpoint="POSTapi-patients"
               value="Sala 12"
               data-component="body">
    <br>
<p>Complemento (opcional, até 100). Must not be greater than 100 characters. Example: <code>Sala 12</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>neighborhood</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.neighborhood"                data-endpoint="POSTapi-patients"
               value="Bela Vista"
               data-component="body">
    <br>
<p>Bairro (até 100). Obrigatório quando <code>address</code> é enviado. Must not be greater than 100 characters. Example: <code>Bela Vista</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>city</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.city"                data-endpoint="POSTapi-patients"
               value="São Paulo"
               data-component="body">
    <br>
<p>Cidade (até 100). Obrigatório quando <code>address</code> é enviado. Must not be greater than 100 characters. Example: <code>São Paulo</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>state</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.state"                data-endpoint="POSTapi-patients"
               value="SP"
               data-component="body">
    <br>
<p>UF: sigla de estado brasileiro (aceita minúsculas; armazenada em maiúsculas). Obrigatório quando <code>address</code> é enviado. Example: <code>SP</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>AC</code></li> <li><code>AL</code></li> <li><code>AP</code></li> <li><code>AM</code></li> <li><code>BA</code></li> <li><code>CE</code></li> <li><code>DF</code></li> <li><code>ES</code></li> <li><code>GO</code></li> <li><code>MA</code></li> <li><code>MT</code></li> <li><code>MS</code></li> <li><code>MG</code></li> <li><code>PA</code></li> <li><code>PB</code></li> <li><code>PR</code></li> <li><code>PE</code></li> <li><code>PI</code></li> <li><code>RJ</code></li> <li><code>RN</code></li> <li><code>RS</code></li> <li><code>RO</code></li> <li><code>RR</code></li> <li><code>SC</code></li> <li><code>SP</code></li> <li><code>SE</code></li> <li><code>TO</code></li></ul>
                    </div>
                                    </details>
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
        &quot;deleted_at&quot;: null,
        &quot;address&quot;: {
            &quot;cep&quot;: &quot;01310100&quot;,
            &quot;street&quot;: &quot;Avenida Paulista&quot;,
            &quot;number&quot;: &quot;1578&quot;,
            &quot;complement&quot;: &quot;Sala 12&quot;,
            &quot;neighborhood&quot;: &quot;Bela Vista&quot;,
            &quot;city&quot;: &quot;S&atilde;o Paulo&quot;,
            &quot;state&quot;: &quot;SP&quot;
        },
        &quot;photo_url&quot;: &quot;http://localhost:8000/api/patients/1/photo?v=1&quot;
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

<p><code>PUT</code> é substituição completa: envie todos os campos. Campos opcionais omitidos (<code>email</code>, <code>description</code>) são
apagados e <code>address</code> omitido ou <code>null</code> remove o endereço.</p>
<p><code>PATCH</code> é atualização parcial: só os campos enviados são validados e alterados (ex.: <code>{"name": "Novo nome"}</code>).
Campos omitidos ficam como estão; <code>address</code> omitido mantém o endereço e <code>null</code> o remove. Enviar um campo
obrigatório vazio continua sendo erro de validação.</p>
<p>A foto não muda por aqui (use as rotas <code>/photo</code>).</p>

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
    \"description\": \"Paciente com miopia leve, retorno anual.\",
    \"address\": {
        \"cep\": \"01310-100\",
        \"street\": \"Avenida Paulista\",
        \"number\": \"1578\",
        \"complement\": \"Sala 12\",
        \"neighborhood\": \"Bela Vista\",
        \"city\": \"São Paulo\",
        \"state\": \"SP\"
    }
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
    "description": "Paciente com miopia leve, retorno anual.",
    "address": {
        "cep": "01310-100",
        "street": "Avenida Paulista",
        "number": "1578",
        "complement": "Sala 12",
        "neighborhood": "Bela Vista",
        "city": "São Paulo",
        "state": "SP"
    }
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
        &quot;deleted_at&quot;: null,
        &quot;address&quot;: {
            &quot;cep&quot;: &quot;01310100&quot;,
            &quot;street&quot;: &quot;Avenida Paulista&quot;,
            &quot;number&quot;: &quot;1578&quot;,
            &quot;complement&quot;: &quot;Sala 12&quot;,
            &quot;neighborhood&quot;: &quot;Bela Vista&quot;,
            &quot;city&quot;: &quot;S&atilde;o Paulo&quot;,
            &quot;state&quot;: &quot;SP&quot;
        },
        &quot;photo_url&quot;: &quot;http://localhost:8000/api/patients/1/photo?v=1&quot;
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
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>address</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
<br>
<p>Endereço (opcional, objeto ou <code>null</code>). No PUT, omitido ou <code>null</code> remove o endereço; no PATCH, omitido mantém o endereço e <code>null</code> o remove. Quando enviado, os subcampos (exceto <code>complement</code>) são obrigatórios. Logradouro, número, complemento e bairro são armazenados criptografados.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>cep</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.cep"                data-endpoint="PUTapi-patients--id-"
               value="01310-100"
               data-component="body">
    <br>
<p>CEP com 8 dígitos, com ou sem máscara (<code>00000-000</code>). Armazenado só com dígitos. Obrigatório quando <code>address</code> é enviado. Must be 8 digits. Example: <code>01310-100</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>street</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.street"                data-endpoint="PUTapi-patients--id-"
               value="Avenida Paulista"
               data-component="body">
    <br>
<p>Logradouro (até 255). Obrigatório quando <code>address</code> é enviado. Must not be greater than 255 characters. Example: <code>Avenida Paulista</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>number</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.number"                data-endpoint="PUTapi-patients--id-"
               value="1578"
               data-component="body">
    <br>
<p>Número (até 20; aceita &quot;S/N&quot;). Obrigatório quando <code>address</code> é enviado. Must not be greater than 20 characters. Example: <code>1578</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>complement</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.complement"                data-endpoint="PUTapi-patients--id-"
               value="Sala 12"
               data-component="body">
    <br>
<p>Complemento (opcional, até 100). Must not be greater than 100 characters. Example: <code>Sala 12</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>neighborhood</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.neighborhood"                data-endpoint="PUTapi-patients--id-"
               value="Bela Vista"
               data-component="body">
    <br>
<p>Bairro (até 100). Obrigatório quando <code>address</code> é enviado. Must not be greater than 100 characters. Example: <code>Bela Vista</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>city</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.city"                data-endpoint="PUTapi-patients--id-"
               value="São Paulo"
               data-component="body">
    <br>
<p>Cidade (até 100). Obrigatório quando <code>address</code> é enviado. Must not be greater than 100 characters. Example: <code>São Paulo</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>state</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="address.state"                data-endpoint="PUTapi-patients--id-"
               value="SP"
               data-component="body">
    <br>
<p>UF: sigla de estado brasileiro (aceita minúsculas; armazenada em maiúsculas). Obrigatório quando <code>address</code> é enviado. Example: <code>SP</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>AC</code></li> <li><code>AL</code></li> <li><code>AP</code></li> <li><code>AM</code></li> <li><code>BA</code></li> <li><code>CE</code></li> <li><code>DF</code></li> <li><code>ES</code></li> <li><code>GO</code></li> <li><code>MA</code></li> <li><code>MT</code></li> <li><code>MS</code></li> <li><code>MG</code></li> <li><code>PA</code></li> <li><code>PB</code></li> <li><code>PR</code></li> <li><code>PE</code></li> <li><code>PI</code></li> <li><code>RJ</code></li> <li><code>RN</code></li> <li><code>RS</code></li> <li><code>RO</code></li> <li><code>RR</code></li> <li><code>SC</code></li> <li><code>SP</code></li> <li><code>SE</code></li> <li><code>TO</code></li></ul>
                    </div>
                                    </details>
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

                    <h2 id="pacientes-GETapi-patients--patient_id--photo">Foto do paciente</h2>

<p>
</p>

<p>A foto (JPEG, até 512x512) do paciente. A URL com <code>?v=&lt;versão&gt;</code> vem em <code>photo_url</code> no recurso do paciente;
a versão muda a cada troca, então a resposta pode ficar em cache no navegador (<code>Cache-Control: private, max-age=86400</code>). <code>404</code> se o paciente não tiver foto, não existir ou estiver na lixeira.</p>

<span id="example-requests-GETapi-patients--patient_id--photo">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/patients/1/photo?v=1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/1/photo"
);

const params = {
    "v": "1",
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

<span id="example-responses-GETapi-patients--patient_id--photo">
            <blockquote>
            <p>Example response (200, Imagem (image/jpeg)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">&lt;bin&aacute;rio JPEG&gt;</code>
 </pre>
            <blockquote>
            <p>Example response (404, Paciente sem foto):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Foto n&atilde;o encontrada.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-patients--patient_id--photo" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-patients--patient_id--photo"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-patients--patient_id--photo"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-patients--patient_id--photo" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-patients--patient_id--photo">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-patients--patient_id--photo" data-method="GET"
      data-path="api/patients/{patient_id}/photo"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-patients--patient_id--photo', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-patients--patient_id--photo"
                    onclick="tryItOut('GETapi-patients--patient_id--photo');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-patients--patient_id--photo"
                    onclick="cancelTryOut('GETapi-patients--patient_id--photo');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-patients--patient_id--photo"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/patients/{patient_id}/photo</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-patients--patient_id--photo"
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
                              name="Accept"                data-endpoint="GETapi-patients--patient_id--photo"
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
               step="any"               name="patient_id"                data-endpoint="GETapi-patients--patient_id--photo"
               value="1"
               data-component="url">
    <br>
<p>ID do paciente. Example: <code>1</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>v</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="v"                data-endpoint="GETapi-patients--patient_id--photo"
               value="1"
               data-component="query">
    <br>
<p>Versão da foto (só para invalidar o cache; ignorada pelo servidor). Example: <code>1</code></p>
            </div>
                </form>

                    <h2 id="pacientes-POSTapi-patients--patient_id--photo">Enviar foto do paciente</h2>

<p>
</p>

<p>Envia ou troca a foto (multipart, campo <code>photo</code>): JPEG, PNG ou WebP (tipo conferido pelo conteúdo), até 2 MB,
entre 100x100 e 4000x4000 px. A imagem é reprocessada no servidor (corte quadrado centralizado, no máximo
512x512, JPEG), o que remove os metadados EXIF (como a localização), e fica criptografada em disco privado.
Gera um registro <code>photo</code> no histórico (sem a imagem). Retorna o paciente com o novo <code>photo_url</code>.</p>

<span id="example-requests-POSTapi-patients--patient_id--photo">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/patients/1/photo" \
    --header "Content-Type: multipart/form-data" \
    --header "Accept: application/json" \
    --form "photo=@/var/www/html/postman/fixtures/photo.png" </code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/1/photo"
);

const headers = {
    "Content-Type": "multipart/form-data",
    "Accept": "application/json",
};

const body = new FormData();
body.append('photo', document.querySelector('input[name="photo"]').files[0]);

fetch(url, {
    method: "POST",
    headers,
    body,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-patients--patient_id--photo">
            <blockquote>
            <p>Example response (200, Foto enviada):</p>
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
        &quot;description&quot;: null,
        &quot;created_at&quot;: &quot;2026-10-26T15:30:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-11-03T10:00:00-03:00&quot;,
        &quot;age&quot;: 30,
        &quot;deleted_at&quot;: null,
        &quot;address&quot;: null,
        &quot;photo_url&quot;: &quot;http://localhost:8000/api/patients/1/photo?v=1&quot;
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
            <p>Example response (422, Tipo inválido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;A foto deve ser uma imagem JPEG, PNG ou WebP.&quot;,
    &quot;errors&quot;: {
        &quot;photo&quot;: [
            &quot;A foto deve ser uma imagem JPEG, PNG ou WebP.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-patients--patient_id--photo" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-patients--patient_id--photo"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-patients--patient_id--photo"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-patients--patient_id--photo" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-patients--patient_id--photo">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-patients--patient_id--photo" data-method="POST"
      data-path="api/patients/{patient_id}/photo"
      data-authed="0"
      data-hasfiles="1"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-patients--patient_id--photo', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-patients--patient_id--photo"
                    onclick="tryItOut('POSTapi-patients--patient_id--photo');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-patients--patient_id--photo"
                    onclick="cancelTryOut('POSTapi-patients--patient_id--photo');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-patients--patient_id--photo"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/patients/{patient_id}/photo</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-patients--patient_id--photo"
               value="multipart/form-data"
               data-component="header">
    <br>
<p>Example: <code>multipart/form-data</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-patients--patient_id--photo"
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
               step="any"               name="patient_id"                data-endpoint="POSTapi-patients--patient_id--photo"
               value="1"
               data-component="url">
    <br>
<p>ID do paciente. Example: <code>1</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>photo</code></b>&nbsp;&nbsp;
<small>file</small>&nbsp;
 &nbsp;
                <input type="file" style="display: none"
                              name="photo"                data-endpoint="POSTapi-patients--patient_id--photo"
               value=""
               data-component="body">
    <br>
<p>Imagem JPEG, PNG ou WebP (conferido pelo conteúdo), até 2 MB, entre 100x100 e 4000x4000 px. Reprocessada no servidor: corte quadrado centralizado, no máximo 512x512, JPEG, sem metadados EXIF. Must be a file. Must not be greater than 2048 kilobytes. Example: <code>postman/fixtures/photo.png</code></p>
        </div>
        </form>

                    <h2 id="pacientes-DELETEapi-patients--patient_id--photo">Remover foto do paciente</h2>

<p>
</p>

<p>Remove a foto (e o arquivo). Gera um registro <code>photo</code> no histórico. <code>404</code> se o paciente não tiver foto.</p>

<span id="example-requests-DELETEapi-patients--patient_id--photo">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/patients/1/photo" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/1/photo"
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

<span id="example-responses-DELETEapi-patients--patient_id--photo">
            <blockquote>
            <p>Example response (204, Foto removida):</p>
        </blockquote>
                <pre>
<code>Empty response</code>
 </pre>
            <blockquote>
            <p>Example response (404, Paciente sem foto):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Foto n&atilde;o encontrada.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-patients--patient_id--photo" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-patients--patient_id--photo"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-patients--patient_id--photo"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-patients--patient_id--photo" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-patients--patient_id--photo">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-patients--patient_id--photo" data-method="DELETE"
      data-path="api/patients/{patient_id}/photo"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-patients--patient_id--photo', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-patients--patient_id--photo"
                    onclick="tryItOut('DELETEapi-patients--patient_id--photo');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-patients--patient_id--photo"
                    onclick="cancelTryOut('DELETEapi-patients--patient_id--photo');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-patients--patient_id--photo"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/patients/{patient_id}/photo</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-patients--patient_id--photo"
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
                              name="Accept"                data-endpoint="DELETEapi-patients--patient_id--photo"
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
               step="any"               name="patient_id"                data-endpoint="DELETEapi-patients--patient_id--photo"
               value="1"
               data-component="url">
    <br>
<p>ID do paciente. Example: <code>1</code></p>
            </div>
                    </form>

                <h1 id="endereco">Endereço</h1>

    <p>Consulta de CEP para preencher o endereço do paciente.</p>

                                <h2 id="endereco-GETapi-cep--code-">Consultar CEP</h2>

<p>
</p>

<p>Busca o endereço de um CEP no ViaCEP (resultado em cache por 24 horas). Aceita máscara (<code>01310-100</code>).
Campos vazios no ViaCEP (CEP geral de cidade pequena) voltam como texto vazio. <code>503</code> quando o ViaCEP não
responde: o endereço pode ser preenchido manualmente.</p>

<span id="example-requests-GETapi-cep--code-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/cep/01310-100" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/cep/01310-100"
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

<span id="example-responses-GETapi-cep--code-">
            <blockquote>
            <p>Example response (200, CEP encontrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;cep&quot;: &quot;01310100&quot;,
        &quot;street&quot;: &quot;Avenida Paulista&quot;,
        &quot;neighborhood&quot;: &quot;Bela Vista&quot;,
        &quot;city&quot;: &quot;S&atilde;o Paulo&quot;,
        &quot;state&quot;: &quot;SP&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, CEP inexistente):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;CEP n&atilde;o encontrado.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Formato inválido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O CEP deve ter 8 d&iacute;gitos.&quot;,
    &quot;errors&quot;: {
        &quot;cep&quot;: [
            &quot;O CEP deve ter 8 d&iacute;gitos.&quot;
        ]
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (503, ViaCEP indisponível):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Servi&ccedil;o de CEP indispon&iacute;vel. Preencha o endere&ccedil;o manualmente.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-cep--code-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-cep--code-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-cep--code-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-cep--code-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-cep--code-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-cep--code-" data-method="GET"
      data-path="api/cep/{code}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-cep--code-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-cep--code-"
                    onclick="tryItOut('GETapi-cep--code-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-cep--code-"
                    onclick="cancelTryOut('GETapi-cep--code-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-cep--code-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/cep/{code}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-cep--code-"
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
                              name="Accept"                data-endpoint="GETapi-cep--code-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="code"                data-endpoint="GETapi-cep--code-"
               value="01310-100"
               data-component="url">
    <br>
<p>CEP com 8 dígitos, com ou sem máscara. Example: <code>01310-100</code></p>
            </div>
                    </form>

                <h1 id="atendimentos">Atendimentos</h1>

    <p>Atendimentos (consultas) dos pacientes: agenda, criação, edição, mudança de status, prontuário oftalmológico,
receita de óculos em PDF e linha do tempo. Datas e horas em ISO-8601 com o deslocamento de São Paulo.
Os campos clínicos livres (<code>reason</code>, <code>notes</code>, <code>diagnosis</code>, <code>observations</code>) são armazenados criptografados.</p>

                                <h2 id="atendimentos-GETapi-patients--patient_id--consultations">Listar atendimentos do paciente</h2>

<p>
</p>

<p>Atendimentos do paciente, do mais recente para o mais antigo (<code>scheduled_at</code> decrescente, desempate por <code>id</code>
decrescente), paginados como a listagem de pacientes, cada um com <code>eye_exam</code> (ou <code>null</code>).
Paciente na lixeira → 404.</p>

<span id="example-requests-GETapi-patients--patient_id--consultations">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/patients/1/consultations?page=1&amp;per_page=10" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/1/consultations"
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

<span id="example-responses-GETapi-patients--patient_id--consultations">
            <blockquote>
            <p>Example response (200, Sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;patient_id&quot;: 1,
            &quot;scheduled_at&quot;: &quot;2026-11-03T14:30:00-03:00&quot;,
            &quot;duration_minutes&quot;: 30,
            &quot;status&quot;: &quot;completed&quot;,
            &quot;professional_name&quot;: &quot;Dra. Helena Martins&quot;,
            &quot;professional_crm&quot;: &quot;CRM-SP 123456&quot;,
            &quot;reason&quot;: &quot;Dificuldade para enxergar de longe.&quot;,
            &quot;notes&quot;: null,
            &quot;eye_exam&quot;: {
                &quot;od&quot;: {
                    &quot;visual_acuity_sc&quot;: &quot;20/40&quot;,
                    &quot;visual_acuity_cc&quot;: &quot;20/20&quot;,
                    &quot;spherical&quot;: -1.25,
                    &quot;cylindrical&quot;: -0.5,
                    &quot;axis&quot;: 180,
                    &quot;addition&quot;: null,
                    &quot;iop&quot;: 14
                },
                &quot;oe&quot;: {
                    &quot;visual_acuity_sc&quot;: &quot;20/50&quot;,
                    &quot;visual_acuity_cc&quot;: &quot;20/20&quot;,
                    &quot;spherical&quot;: -1,
                    &quot;cylindrical&quot;: 0,
                    &quot;axis&quot;: null,
                    &quot;addition&quot;: null,
                    &quot;iop&quot;: 15
                },
                &quot;diagnosis&quot;: &quot;Miopia leve com astigmatismo.&quot;,
                &quot;observations&quot;: null,
                &quot;updated_at&quot;: &quot;2026-11-03T15:00:00-03:00&quot;
            },
            &quot;created_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;,
            &quot;updated_at&quot;: &quot;2026-11-03T15:00:00-03:00&quot;
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;http://localhost:8000/api/patients/1/consultations?page=1&quot;,
        &quot;last&quot;: &quot;http://localhost:8000/api/patients/1/consultations?page=1&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;from&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;links&quot;: [],
        &quot;path&quot;: &quot;http://localhost:8000/api/patients/1/consultations&quot;,
        &quot;per_page&quot;: 10,
        &quot;to&quot;: 1,
        &quot;total&quot;: 1
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
<span id="execution-results-GETapi-patients--patient_id--consultations" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-patients--patient_id--consultations"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-patients--patient_id--consultations"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-patients--patient_id--consultations" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-patients--patient_id--consultations">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-patients--patient_id--consultations" data-method="GET"
      data-path="api/patients/{patient_id}/consultations"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-patients--patient_id--consultations', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-patients--patient_id--consultations"
                    onclick="tryItOut('GETapi-patients--patient_id--consultations');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-patients--patient_id--consultations"
                    onclick="cancelTryOut('GETapi-patients--patient_id--consultations');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-patients--patient_id--consultations"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/patients/{patient_id}/consultations</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-patients--patient_id--consultations"
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
                              name="Accept"                data-endpoint="GETapi-patients--patient_id--consultations"
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
               step="any"               name="patient_id"                data-endpoint="GETapi-patients--patient_id--consultations"
               value="1"
               data-component="url">
    <br>
<p>ID do paciente. Example: <code>1</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-patients--patient_id--consultations"
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
               step="any"               name="per_page"                data-endpoint="GETapi-patients--patient_id--consultations"
               value="10"
               data-component="query">
    <br>
<p>Itens por página. Padrão: 10; máximo: 100. Example: <code>10</code></p>
            </div>
                </form>

                    <h2 id="atendimentos-POSTapi-patients--patient_id--consultations">Agendar atendimento</h2>

<p>
</p>

<p>Cria um atendimento com <code>status = scheduled</code>. <code>422</code> em <code>scheduled_at</code> se o mesmo profissional (nome sem
diferenciar maiúsculas nem acentos) já tiver um atendimento não cancelado e não <code>no_show</code> com horário sobreposto
(intervalos <code>[início, início + duração)</code>; horários que só se encostam não conflitam).</p>

<span id="example-requests-POSTapi-patients--patient_id--consultations">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/patients/1/consultations" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"scheduled_at\": \"2026-11-03T14:30:00-03:00\",
    \"duration_minutes\": 30,
    \"professional_name\": \"Dra. Helena Martins\",
    \"professional_crm\": \"CRM-SP 123456\",
    \"reason\": \"Dificuldade para enxergar de longe.\",
    \"notes\": \"Paciente usa óculos há 5 anos.\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/1/consultations"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "scheduled_at": "2026-11-03T14:30:00-03:00",
    "duration_minutes": 30,
    "professional_name": "Dra. Helena Martins",
    "professional_crm": "CRM-SP 123456",
    "reason": "Dificuldade para enxergar de longe.",
    "notes": "Paciente usa óculos há 5 anos."
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-patients--patient_id--consultations">
            <blockquote>
            <p>Example response (201, Atendimento criado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;patient_id&quot;: 1,
        &quot;scheduled_at&quot;: &quot;2026-11-03T14:30:00-03:00&quot;,
        &quot;duration_minutes&quot;: 30,
        &quot;status&quot;: &quot;scheduled&quot;,
        &quot;professional_name&quot;: &quot;Dra. Helena Martins&quot;,
        &quot;professional_crm&quot;: &quot;CRM-SP 123456&quot;,
        &quot;reason&quot;: &quot;Dificuldade para enxergar de longe.&quot;,
        &quot;notes&quot;: null,
        &quot;eye_exam&quot;: null,
        &quot;created_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;
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
            <p>Example response (422, Conflito de horário):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Este profissional j&aacute; tem um atendimento neste hor&aacute;rio.&quot;,
    &quot;errors&quot;: {
        &quot;scheduled_at&quot;: [
            &quot;Este profissional j&aacute; tem um atendimento neste hor&aacute;rio.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-patients--patient_id--consultations" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-patients--patient_id--consultations"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-patients--patient_id--consultations"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-patients--patient_id--consultations" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-patients--patient_id--consultations">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-patients--patient_id--consultations" data-method="POST"
      data-path="api/patients/{patient_id}/consultations"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-patients--patient_id--consultations', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-patients--patient_id--consultations"
                    onclick="tryItOut('POSTapi-patients--patient_id--consultations');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-patients--patient_id--consultations"
                    onclick="cancelTryOut('POSTapi-patients--patient_id--consultations');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-patients--patient_id--consultations"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/patients/{patient_id}/consultations</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-patients--patient_id--consultations"
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
                              name="Accept"                data-endpoint="POSTapi-patients--patient_id--consultations"
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
               step="any"               name="patient_id"                data-endpoint="POSTapi-patients--patient_id--consultations"
               value="1"
               data-component="url">
    <br>
<p>ID do paciente. Example: <code>1</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>scheduled_at</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="scheduled_at"                data-endpoint="POSTapi-patients--patient_id--consultations"
               value="2026-11-03T14:30:00-03:00"
               data-component="body">
    <br>
<p>Data e hora de início, ISO-8601 com fuso (<code>2026-11-03T14:30:00-03:00</code>) ou sem fuso (<code>2026-11-03T14:30</code>, interpretado em America/Sao_Paulo). Example: <code>2026-11-03T14:30:00-03:00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>duration_minutes</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="duration_minutes"                data-endpoint="POSTapi-patients--patient_id--consultations"
               value="30"
               data-component="body">
    <br>
<p>Duração em minutos (10 a 240). Padrão: 30. Must be between 10 and 240. Example: <code>30</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>professional_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="professional_name"                data-endpoint="POSTapi-patients--patient_id--consultations"
               value="Dra. Helena Martins"
               data-component="body">
    <br>
<p>Nome do profissional (3 a 255 caracteres). Um mesmo nome (sem diferenciar maiúsculas nem acentos) não pode ter dois atendimentos ativos com horários sobrepostos. Must be at least 3 characters. Must not be greater than 255 characters. Example: <code>Dra. Helena Martins</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>professional_crm</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="professional_crm"                data-endpoint="POSTapi-patients--patient_id--consultations"
               value="CRM-SP 123456"
               data-component="body">
    <br>
<p>CRM do profissional (opcional, até 20 caracteres). Must not be greater than 20 characters. Example: <code>CRM-SP 123456</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>reason</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="reason"                data-endpoint="POSTapi-patients--patient_id--consultations"
               value="Dificuldade para enxergar de longe."
               data-component="body">
    <br>
<p>Queixa principal (opcional, até 2000 caracteres). Armazenada criptografada. Must not be greater than 2000 characters. Example: <code>Dificuldade para enxergar de longe.</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>notes</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="notes"                data-endpoint="POSTapi-patients--patient_id--consultations"
               value="Paciente usa óculos há 5 anos."
               data-component="body">
    <br>
<p>Anotações (opcional, até 5000 caracteres). Armazenadas criptografadas. Único campo editável em atendimentos finalizados. Must not be greater than 5000 characters. Example: <code>Paciente usa óculos há 5 anos.</code></p>
        </div>
        </form>

                    <h2 id="atendimentos-GETapi-patients--patient_id--timeline">Linha do tempo do paciente</h2>

<p>
</p>

<p>Atendimentos e registros do histórico de alterações do paciente numa só lista, do mais recente para o mais
antigo, paginada (<code>page</code>, <code>per_page</code> como na listagem). <code>occurred_at</code> é o <code>scheduled_at</code> do atendimento ou
o <code>created_at</code> do registro do histórico. Desempate: atendimentos antes de registros do histórico, depois
<code>id</code> decrescente. Paciente na lixeira → 404.</p>

<span id="example-requests-GETapi-patients--patient_id--timeline">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/patients/1/timeline?page=1&amp;per_page=10" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/patients/1/timeline"
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

<span id="example-responses-GETapi-patients--patient_id--timeline">
            <blockquote>
            <p>Example response (200, Sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;type&quot;: &quot;consultation&quot;,
            &quot;occurred_at&quot;: &quot;2026-11-03T14:30:00-03:00&quot;,
            &quot;consultation&quot;: {
                &quot;id&quot;: 1,
                &quot;patient_id&quot;: 1,
                &quot;scheduled_at&quot;: &quot;2026-11-03T14:30:00-03:00&quot;,
                &quot;duration_minutes&quot;: 30,
                &quot;status&quot;: &quot;scheduled&quot;,
                &quot;professional_name&quot;: &quot;Dra. Helena Martins&quot;,
                &quot;professional_crm&quot;: &quot;CRM-SP 123456&quot;,
                &quot;reason&quot;: null,
                &quot;notes&quot;: null,
                &quot;eye_exam&quot;: null,
                &quot;created_at&quot;: &quot;2026-10-27T10:05:00-03:00&quot;,
                &quot;updated_at&quot;: &quot;2026-10-27T10:05:00-03:00&quot;
            }
        },
        {
            &quot;type&quot;: &quot;audit&quot;,
            &quot;occurred_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;,
            &quot;audit&quot;: {
                &quot;id&quot;: 1,
                &quot;event&quot;: &quot;created&quot;,
                &quot;changes&quot;: {
                    &quot;name&quot;: {
                        &quot;old&quot;: null,
                        &quot;new&quot;: &quot;Maria da Silva&quot;
                    }
                },
                &quot;ip&quot;: &quot;172.18.0.1&quot;,
                &quot;user_agent&quot;: &quot;Mozilla/5.0 (X11; Linux x86_64)&quot;,
                &quot;user_id&quot;: null,
                &quot;created_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;
            }
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;http://localhost:8000/api/patients/1/timeline?page=1&quot;,
        &quot;last&quot;: &quot;http://localhost:8000/api/patients/1/timeline?page=1&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;from&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;links&quot;: [],
        &quot;path&quot;: &quot;http://localhost:8000/api/patients/1/timeline&quot;,
        &quot;per_page&quot;: 10,
        &quot;to&quot;: 2,
        &quot;total&quot;: 2
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
<span id="execution-results-GETapi-patients--patient_id--timeline" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-patients--patient_id--timeline"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-patients--patient_id--timeline"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-patients--patient_id--timeline" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-patients--patient_id--timeline">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-patients--patient_id--timeline" data-method="GET"
      data-path="api/patients/{patient_id}/timeline"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-patients--patient_id--timeline', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-patients--patient_id--timeline"
                    onclick="tryItOut('GETapi-patients--patient_id--timeline');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-patients--patient_id--timeline"
                    onclick="cancelTryOut('GETapi-patients--patient_id--timeline');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-patients--patient_id--timeline"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/patients/{patient_id}/timeline</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-patients--patient_id--timeline"
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
                              name="Accept"                data-endpoint="GETapi-patients--patient_id--timeline"
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
               step="any"               name="patient_id"                data-endpoint="GETapi-patients--patient_id--timeline"
               value="1"
               data-component="url">
    <br>
<p>ID do paciente. Example: <code>1</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-patients--patient_id--timeline"
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
               step="any"               name="per_page"                data-endpoint="GETapi-patients--patient_id--timeline"
               value="10"
               data-component="query">
    <br>
<p>Itens por página. Padrão: 10; máximo: 100. Example: <code>10</code></p>
            </div>
                </form>

                    <h2 id="atendimentos-GETapi-consultations">Agenda</h2>

<p>
</p>

<p>Atendimentos que começam entre <code>date_from</code> e <code>date_to</code> (dias de São Paulo, inclusive), em ordem crescente de
<code>scheduled_at</code> (desempate por <code>id</code>), <strong>sem paginação</strong>, cada um com <code>patient</code> (<code>id</code> e <code>name</code>). Pacientes na
lixeira não aparecem. Intervalo máximo de 31 dias; <code>date_to</code> anterior a <code>date_from</code> → 422.</p>

<span id="example-requests-GETapi-consultations">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/consultations?date_from=2026-11-02&amp;date_to=2026-11-08&amp;status=scheduled%2Cconfirmed&amp;professional=helena" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/consultations"
);

const params = {
    "date_from": "2026-11-02",
    "date_to": "2026-11-08",
    "status": "scheduled,confirmed",
    "professional": "helena",
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

<span id="example-responses-GETapi-consultations">
            <blockquote>
            <p>Example response (200, Sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 2,
            &quot;patient_id&quot;: 1,
            &quot;patient&quot;: {
                &quot;id&quot;: 1,
                &quot;name&quot;: &quot;Maria da Silva&quot;
            },
            &quot;scheduled_at&quot;: &quot;2026-11-04T09:00:00-03:00&quot;,
            &quot;duration_minutes&quot;: 30,
            &quot;status&quot;: &quot;confirmed&quot;,
            &quot;professional_name&quot;: &quot;Dra. Helena Martins&quot;,
            &quot;professional_crm&quot;: &quot;CRM-SP 123456&quot;,
            &quot;reason&quot;: &quot;Retorno.&quot;,
            &quot;notes&quot;: null,
            &quot;eye_exam&quot;: null,
            &quot;created_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;,
            &quot;updated_at&quot;: &quot;2026-10-28T08:00:00-03:00&quot;
        }
    ]
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Intervalo maior que 31 dias):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O intervalo da agenda n&atilde;o pode passar de 31 dias.&quot;,
    &quot;errors&quot;: {
        &quot;date_to&quot;: [
            &quot;O intervalo da agenda n&atilde;o pode passar de 31 dias.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-consultations" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-consultations"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-consultations"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-consultations" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-consultations">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-consultations" data-method="GET"
      data-path="api/consultations"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-consultations', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-consultations"
                    onclick="tryItOut('GETapi-consultations');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-consultations"
                    onclick="cancelTryOut('GETapi-consultations');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-consultations"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/consultations</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-consultations"
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
                              name="Accept"                data-endpoint="GETapi-consultations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>date_from</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="date_from"                data-endpoint="GETapi-consultations"
               value="2026-11-02"
               data-component="query">
    <br>
<p>Primeiro dia (AAAA-MM-DD, dia de São Paulo, inclusive). Padrão: hoje (ou <code>date_to</code> - 6 quando só <code>date_to</code> é enviado). Must be a valid date in the format <code>Y-m-d</code>. Example: <code>2026-11-02</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>date_to</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="date_to"                data-endpoint="GETapi-consultations"
               value="2026-11-08"
               data-component="query">
    <br>
<p>Último dia (AAAA-MM-DD, inclusive). Padrão: <code>date_from</code> + 6. O intervalo pode ter no máximo 31 dias. Must be a valid date in the format <code>Y-m-d</code>. Example: <code>2026-11-08</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="GETapi-consultations"
               value="scheduled,confirmed"
               data-component="query">
    <br>
<p>Um ou vários status separados por vírgula: <code>scheduled</code>, <code>confirmed</code>, <code>completed</code>, <code>no_show</code>, <code>cancelled</code>. Valor desconhecido → 422. Example: <code>scheduled,confirmed</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>professional</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="professional"                data-endpoint="GETapi-consultations"
               value="helena"
               data-component="query">
    <br>
<p>Parte do nome do profissional (sem diferenciar maiúsculas nem acentos). Must not be greater than 255 characters. Example: <code>helena</code></p>
            </div>
                </form>

                    <h2 id="atendimentos-GETapi-consultations--id-">Detalhar atendimento</h2>

<p>
</p>

<p>Com <code>patient</code> (<code>id</code> e <code>name</code>) e <code>eye_exam</code>. <code>404</code> se não existir ou se o paciente estiver na lixeira.</p>

<span id="example-requests-GETapi-consultations--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/consultations/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/consultations/1"
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

<span id="example-responses-GETapi-consultations--id-">
            <blockquote>
            <p>Example response (200, Sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;patient_id&quot;: 1,
        &quot;patient&quot;: {
            &quot;id&quot;: 1,
            &quot;name&quot;: &quot;Maria da Silva&quot;
        },
        &quot;scheduled_at&quot;: &quot;2026-11-03T14:30:00-03:00&quot;,
        &quot;duration_minutes&quot;: 30,
        &quot;status&quot;: &quot;scheduled&quot;,
        &quot;professional_name&quot;: &quot;Dra. Helena Martins&quot;,
        &quot;professional_crm&quot;: &quot;CRM-SP 123456&quot;,
        &quot;reason&quot;: &quot;Dificuldade para enxergar de longe.&quot;,
        &quot;notes&quot;: null,
        &quot;eye_exam&quot;: null,
        &quot;created_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Atendimento não encontrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Atendimento n&atilde;o encontrado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-consultations--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-consultations--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-consultations--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-consultations--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-consultations--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-consultations--id-" data-method="GET"
      data-path="api/consultations/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-consultations--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-consultations--id-"
                    onclick="tryItOut('GETapi-consultations--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-consultations--id-"
                    onclick="cancelTryOut('GETapi-consultations--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-consultations--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/consultations/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-consultations--id-"
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
                              name="Accept"                data-endpoint="GETapi-consultations--id-"
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
               step="any"               name="id"                data-endpoint="GETapi-consultations--id-"
               value="1"
               data-component="url">
    <br>
<p>ID do atendimento. Example: <code>1</code></p>
            </div>
                    </form>

                    <h2 id="atendimentos-PUTapi-consultations--id-">Atualizar atendimento</h2>

<p>
</p>

<p>Substituição completa (PUT) dos campos editáveis (os mesmos da criação; opcionais omitidos são apagados e
a duração volta a 30). Em atendimento finalizado (<code>completed</code>, <code>no_show</code>, <code>cancelled</code>) só <code>notes</code> pode
mudar: qualquer outro campo diferente do atual → 422 naquele campo. O conflito de horário é verificado
ignorando o próprio atendimento.</p>

<span id="example-requests-PUTapi-consultations--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/consultations/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"scheduled_at\": \"2026-11-03T14:30:00-03:00\",
    \"duration_minutes\": 30,
    \"professional_name\": \"Dra. Helena Martins\",
    \"professional_crm\": \"CRM-SP 123456\",
    \"reason\": \"Dificuldade para enxergar de longe.\",
    \"notes\": \"Paciente usa óculos há 5 anos.\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/consultations/1"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "scheduled_at": "2026-11-03T14:30:00-03:00",
    "duration_minutes": 30,
    "professional_name": "Dra. Helena Martins",
    "professional_crm": "CRM-SP 123456",
    "reason": "Dificuldade para enxergar de longe.",
    "notes": "Paciente usa óculos há 5 anos."
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-consultations--id-">
            <blockquote>
            <p>Example response (200, Atendimento atualizado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;patient_id&quot;: 1,
        &quot;patient&quot;: {
            &quot;id&quot;: 1,
            &quot;name&quot;: &quot;Maria da Silva&quot;
        },
        &quot;scheduled_at&quot;: &quot;2026-11-03T15:00:00-03:00&quot;,
        &quot;duration_minutes&quot;: 45,
        &quot;status&quot;: &quot;scheduled&quot;,
        &quot;professional_name&quot;: &quot;Dra. Helena Martins&quot;,
        &quot;professional_crm&quot;: &quot;CRM-SP 123456&quot;,
        &quot;reason&quot;: &quot;Dificuldade para enxergar de longe.&quot;,
        &quot;notes&quot;: &quot;Trazer &oacute;culos atuais.&quot;,
        &quot;eye_exam&quot;: null,
        &quot;created_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-10-27T11:00:00-03:00&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Atendimento não encontrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Atendimento n&atilde;o encontrado.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Atendimento finalizado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O campo data e hora n&atilde;o pode ser alterado em um atendimento finalizado (s&oacute; as anota&ccedil;&otilde;es podem mudar).&quot;,
    &quot;errors&quot;: {
        &quot;scheduled_at&quot;: [
            &quot;O campo data e hora n&atilde;o pode ser alterado em um atendimento finalizado (s&oacute; as anota&ccedil;&otilde;es podem mudar).&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-consultations--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-consultations--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-consultations--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-consultations--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-consultations--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-consultations--id-" data-method="PUT"
      data-path="api/consultations/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-consultations--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-consultations--id-"
                    onclick="tryItOut('PUTapi-consultations--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-consultations--id-"
                    onclick="cancelTryOut('PUTapi-consultations--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-consultations--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/consultations/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-consultations--id-"
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
                              name="Accept"                data-endpoint="PUTapi-consultations--id-"
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
               step="any"               name="id"                data-endpoint="PUTapi-consultations--id-"
               value="1"
               data-component="url">
    <br>
<p>ID do atendimento. Example: <code>1</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>scheduled_at</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="scheduled_at"                data-endpoint="PUTapi-consultations--id-"
               value="2026-11-03T14:30:00-03:00"
               data-component="body">
    <br>
<p>Data e hora de início, ISO-8601 com fuso (<code>2026-11-03T14:30:00-03:00</code>) ou sem fuso (<code>2026-11-03T14:30</code>, interpretado em America/Sao_Paulo). Example: <code>2026-11-03T14:30:00-03:00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>duration_minutes</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="duration_minutes"                data-endpoint="PUTapi-consultations--id-"
               value="30"
               data-component="body">
    <br>
<p>Duração em minutos (10 a 240). Padrão: 30. Must be between 10 and 240. Example: <code>30</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>professional_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="professional_name"                data-endpoint="PUTapi-consultations--id-"
               value="Dra. Helena Martins"
               data-component="body">
    <br>
<p>Nome do profissional (3 a 255 caracteres). Um mesmo nome (sem diferenciar maiúsculas nem acentos) não pode ter dois atendimentos ativos com horários sobrepostos. Must be at least 3 characters. Must not be greater than 255 characters. Example: <code>Dra. Helena Martins</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>professional_crm</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="professional_crm"                data-endpoint="PUTapi-consultations--id-"
               value="CRM-SP 123456"
               data-component="body">
    <br>
<p>CRM do profissional (opcional, até 20 caracteres). Must not be greater than 20 characters. Example: <code>CRM-SP 123456</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>reason</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="reason"                data-endpoint="PUTapi-consultations--id-"
               value="Dificuldade para enxergar de longe."
               data-component="body">
    <br>
<p>Queixa principal (opcional, até 2000 caracteres). Armazenada criptografada. Must not be greater than 2000 characters. Example: <code>Dificuldade para enxergar de longe.</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>notes</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="notes"                data-endpoint="PUTapi-consultations--id-"
               value="Paciente usa óculos há 5 anos."
               data-component="body">
    <br>
<p>Anotações (opcional, até 5000 caracteres). Armazenadas criptografadas. Único campo editável em atendimentos finalizados. Must not be greater than 5000 characters. Example: <code>Paciente usa óculos há 5 anos.</code></p>
        </div>
        </form>

                    <h2 id="atendimentos-PATCHapi-consultations--consultation_id--status">Alterar status do atendimento</h2>

<p>
</p>

<p>Transições permitidas: <code>scheduled</code> → <code>confirmed</code>, <code>completed</code>, <code>no_show</code>, <code>cancelled</code>;
<code>confirmed</code> → <code>completed</code>, <code>no_show</code>, <code>cancelled</code>. <code>completed</code>, <code>no_show</code> e <code>cancelled</code> são finais.
Outras (inclusive para o mesmo status) → 422 em <code>status</code>.</p>

<span id="example-requests-PATCHapi-consultations--consultation_id--status">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:8000/api/consultations/1/status" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"status\": \"confirmed\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/consultations/1/status"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "status": "confirmed"
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-consultations--consultation_id--status">
            <blockquote>
            <p>Example response (200, Status alterado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;patient_id&quot;: 1,
        &quot;patient&quot;: {
            &quot;id&quot;: 1,
            &quot;name&quot;: &quot;Maria da Silva&quot;
        },
        &quot;scheduled_at&quot;: &quot;2026-11-03T14:30:00-03:00&quot;,
        &quot;duration_minutes&quot;: 30,
        &quot;status&quot;: &quot;confirmed&quot;,
        &quot;professional_name&quot;: &quot;Dra. Helena Martins&quot;,
        &quot;professional_crm&quot;: &quot;CRM-SP 123456&quot;,
        &quot;reason&quot;: null,
        &quot;notes&quot;: null,
        &quot;eye_exam&quot;: null,
        &quot;created_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-10-28T08:00:00-03:00&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Atendimento não encontrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Atendimento n&atilde;o encontrado.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Transição inválida):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;N&atilde;o &eacute; poss&iacute;vel alterar o status de \&quot;realizado\&quot; para \&quot;cancelado\&quot;.&quot;,
    &quot;errors&quot;: {
        &quot;status&quot;: [
            &quot;N&atilde;o &eacute; poss&iacute;vel alterar o status de \&quot;realizado\&quot; para \&quot;cancelado\&quot;.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-consultations--consultation_id--status" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-consultations--consultation_id--status"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-consultations--consultation_id--status"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-consultations--consultation_id--status" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-consultations--consultation_id--status">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-consultations--consultation_id--status" data-method="PATCH"
      data-path="api/consultations/{consultation_id}/status"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-consultations--consultation_id--status', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-consultations--consultation_id--status"
                    onclick="tryItOut('PATCHapi-consultations--consultation_id--status');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-consultations--consultation_id--status"
                    onclick="cancelTryOut('PATCHapi-consultations--consultation_id--status');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-consultations--consultation_id--status"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/consultations/{consultation_id}/status</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-consultations--consultation_id--status"
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
                              name="Accept"                data-endpoint="PATCHapi-consultations--consultation_id--status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>consultation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="consultation_id"                data-endpoint="PATCHapi-consultations--consultation_id--status"
               value="1"
               data-component="url">
    <br>
<p>ID do atendimento. Example: <code>1</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="PATCHapi-consultations--consultation_id--status"
               value="confirmed"
               data-component="body">
    <br>
<p>Novo status: <code>confirmed</code>, <code>completed</code>, <code>no_show</code> ou <code>cancelled</code> (conforme a tabela de transições). Example: <code>confirmed</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>scheduled</code></li> <li><code>confirmed</code></li> <li><code>completed</code></li> <li><code>no_show</code></li> <li><code>cancelled</code></li></ul>
        </div>
        </form>

                    <h2 id="atendimentos-PUTapi-consultations--consultation_id--eye-exam">Registrar prontuário oftalmológico</h2>

<p>
</p>

<p>Cria ou substitui (PUT) o prontuário do atendimento: acuidade visual, refração (esférico, cilíndrico, eixo,
adição) e pressão intraocular de cada olho (<code>od</code> = direito, <code>oe</code> = esquerdo), diagnóstico e observações.
Todos os campos são opcionais; os omitidos são apagados. Só para atendimentos <code>completed</code> (senão 422 em
<code>status</code>). Retorna o atendimento com o <code>eye_exam</code> gravado.</p>

<span id="example-requests-PUTapi-consultations--consultation_id--eye-exam">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/consultations/1/eye-exam" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"diagnosis\": \"Miopia leve com astigmatismo.\",
    \"od\": {
        \"visual_acuity_sc\": \"20\\/40\",
        \"visual_acuity_cc\": \"20\\/20\",
        \"spherical\": -1.25,
        \"cylindrical\": -0.5,
        \"axis\": 180,
        \"iop\": 14
    },
    \"oe\": {
        \"visual_acuity_sc\": \"20\\/50\",
        \"visual_acuity_cc\": \"20\\/20\",
        \"spherical\": -1,
        \"cylindrical\": 0,
        \"iop\": 15
    }
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/consultations/1/eye-exam"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "diagnosis": "Miopia leve com astigmatismo.",
    "od": {
        "visual_acuity_sc": "20\/40",
        "visual_acuity_cc": "20\/20",
        "spherical": -1.25,
        "cylindrical": -0.5,
        "axis": 180,
        "iop": 14
    },
    "oe": {
        "visual_acuity_sc": "20\/50",
        "visual_acuity_cc": "20\/20",
        "spherical": -1,
        "cylindrical": 0,
        "iop": 15
    }
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-consultations--consultation_id--eye-exam">
            <blockquote>
            <p>Example response (200, Prontuário gravado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;patient_id&quot;: 1,
        &quot;patient&quot;: {
            &quot;id&quot;: 1,
            &quot;name&quot;: &quot;Maria da Silva&quot;
        },
        &quot;scheduled_at&quot;: &quot;2026-11-03T14:30:00-03:00&quot;,
        &quot;duration_minutes&quot;: 30,
        &quot;status&quot;: &quot;completed&quot;,
        &quot;professional_name&quot;: &quot;Dra. Helena Martins&quot;,
        &quot;professional_crm&quot;: &quot;CRM-SP 123456&quot;,
        &quot;reason&quot;: &quot;Dificuldade para enxergar de longe.&quot;,
        &quot;notes&quot;: null,
        &quot;eye_exam&quot;: {
            &quot;od&quot;: {
                &quot;visual_acuity_sc&quot;: &quot;20/40&quot;,
                &quot;visual_acuity_cc&quot;: &quot;20/20&quot;,
                &quot;spherical&quot;: -1.25,
                &quot;cylindrical&quot;: -0.5,
                &quot;axis&quot;: 180,
                &quot;addition&quot;: null,
                &quot;iop&quot;: 14
            },
            &quot;oe&quot;: {
                &quot;visual_acuity_sc&quot;: &quot;20/50&quot;,
                &quot;visual_acuity_cc&quot;: &quot;20/20&quot;,
                &quot;spherical&quot;: -1,
                &quot;cylindrical&quot;: 0,
                &quot;axis&quot;: null,
                &quot;addition&quot;: null,
                &quot;iop&quot;: 15
            },
            &quot;diagnosis&quot;: &quot;Miopia leve com astigmatismo.&quot;,
            &quot;observations&quot;: null,
            &quot;updated_at&quot;: &quot;2026-11-03T15:00:00-03:00&quot;
        },
        &quot;created_at&quot;: &quot;2026-10-27T10:00:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-11-03T15:00:00-03:00&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Atendimento não encontrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Atendimento n&atilde;o encontrado.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Atendimento não realizado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O prontu&aacute;rio s&oacute; pode ser registrado em atendimentos realizados.&quot;,
    &quot;errors&quot;: {
        &quot;status&quot;: [
            &quot;O prontu&aacute;rio s&oacute; pode ser registrado em atendimentos realizados.&quot;
        ]
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Eixo sem cilíndrico):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O campo eixo (OE) deve ficar vazio quando o cil&iacute;ndrico &eacute; zero ou n&atilde;o informado.&quot;,
    &quot;errors&quot;: {
        &quot;oe.axis&quot;: [
            &quot;O campo eixo (OE) deve ficar vazio quando o cil&iacute;ndrico &eacute; zero ou n&atilde;o informado.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-consultations--consultation_id--eye-exam" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-consultations--consultation_id--eye-exam"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-consultations--consultation_id--eye-exam"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-consultations--consultation_id--eye-exam" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-consultations--consultation_id--eye-exam">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-consultations--consultation_id--eye-exam" data-method="PUT"
      data-path="api/consultations/{consultation_id}/eye-exam"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-consultations--consultation_id--eye-exam', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-consultations--consultation_id--eye-exam"
                    onclick="tryItOut('PUTapi-consultations--consultation_id--eye-exam');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-consultations--consultation_id--eye-exam"
                    onclick="cancelTryOut('PUTapi-consultations--consultation_id--eye-exam');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-consultations--consultation_id--eye-exam"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/consultations/{consultation_id}/eye-exam</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
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
                              name="Accept"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>consultation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="consultation_id"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="1"
               data-component="url">
    <br>
<p>ID do atendimento. Example: <code>1</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>diagnosis</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="diagnosis"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="Miopia leve com astigmatismo."
               data-component="body">
    <br>
<p>Diagnóstico (opcional, até 2000 caracteres). Armazenado criptografado. Must not be greater than 2000 characters. Example: <code>Miopia leve com astigmatismo.</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>observations</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="observations"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value=""
               data-component="body">
    <br>
<p>Observações (opcional, até 5000 caracteres). Armazenadas criptografadas. Must not be greater than 5000 characters.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>od</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
<br>
<p>Medidas do olho OD (direito). Todas opcionais.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>visual_acuity_sc</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="od.visual_acuity_sc"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="20/40"
               data-component="body">
    <br>
<p>Acuidade visual sem correção, Snellen <code>20/N</code> (N entre 10 e 400). Example: <code>20/40</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>visual_acuity_cc</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="od.visual_acuity_cc"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="20/20"
               data-component="body">
    <br>
<p>Acuidade visual com correção, Snellen <code>20/N</code> (N entre 10 e 400). Example: <code>20/20</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>spherical</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="od.spherical"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="-1.25"
               data-component="body">
    <br>
<p>Esférico, de -30.00 a +30.00, múltiplo de 0.25. Must be between -30 and 30. Example: <code>-1.25</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>cylindrical</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="od.cylindrical"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="-0.5"
               data-component="body">
    <br>
<p>Cilíndrico, de -10.00 a 0.00, múltiplo de 0.25. Must be between -10 and 0. Example: <code>-0.5</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>axis</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="od.axis"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="180"
               data-component="body">
    <br>
<p>Eixo em graus (0 a 180). Obrigatório quando o cilíndrico é diferente de zero; deve ser <code>null</code> quando é zero ou ausente. Must be between 0 and 180. Example: <code>180</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>addition</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="od.addition"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value=""
               data-component="body">
    <br>
<p>Adição para perto, de +0.75 a +4.00, múltiplo de 0.25. Must be between 0.75 and 4.</p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>iop</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="od.iop"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="14"
               data-component="body">
    <br>
<p>Pressão intraocular em mmHg (inteiro, 5 a 60). Must be between 5 and 60. Example: <code>14</code></p>
                    </div>
                                    </details>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>oe</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
<br>
<p>Medidas do olho OE (esquerdo). Todas opcionais.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>visual_acuity_sc</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="oe.visual_acuity_sc"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="20/50"
               data-component="body">
    <br>
<p>Acuidade visual sem correção, Snellen <code>20/N</code> (N entre 10 e 400). Example: <code>20/50</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>visual_acuity_cc</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="oe.visual_acuity_cc"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="20/20"
               data-component="body">
    <br>
<p>Acuidade visual com correção, Snellen <code>20/N</code> (N entre 10 e 400). Example: <code>20/20</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>spherical</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="oe.spherical"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="-1"
               data-component="body">
    <br>
<p>Esférico, de -30.00 a +30.00, múltiplo de 0.25. Must be between -30 and 30. Example: <code>-1</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>cylindrical</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="oe.cylindrical"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="0"
               data-component="body">
    <br>
<p>Cilíndrico, de -10.00 a 0.00, múltiplo de 0.25. Must be between -10 and 0. Example: <code>0</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>axis</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="oe.axis"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value=""
               data-component="body">
    <br>
<p>Eixo em graus (0 a 180). Obrigatório quando o cilíndrico é diferente de zero; deve ser <code>null</code> quando é zero ou ausente. Must be between 0 and 180.</p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>addition</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="oe.addition"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value=""
               data-component="body">
    <br>
<p>Adição para perto, de +0.75 a +4.00, múltiplo de 0.25. Must be between 0.75 and 4.</p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>iop</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="oe.iop"                data-endpoint="PUTapi-consultations--consultation_id--eye-exam"
               value="15"
               data-component="body">
    <br>
<p>Pressão intraocular em mmHg (inteiro, 5 a 60). Must be between 5 and 60. Example: <code>15</code></p>
                    </div>
                                    </details>
        </div>
        </form>

                    <h2 id="atendimentos-GETapi-consultations--consultation_id--prescription">Receita de óculos (PDF)</h2>

<p>
</p>

<p>Baixa a receita de óculos do atendimento em PDF (<code>receita-&lt;id&gt;-AAAAMMDD.pdf</code>, com a data do atendimento):
paciente (nome, data de nascimento e idade), data, tabela OD/OE com esférico, cilíndrico, eixo e adição,
profissional e CRM, e linha para assinatura. <code>422</code> se o atendimento não tiver prontuário com ao menos um
valor de refração. O &quot;Try it out&quot; não exibe o PDF; para baixar, abra a URL no navegador.</p>

<span id="example-requests-GETapi-consultations--consultation_id--prescription">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/consultations/1/prescription" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/consultations/1/prescription"
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

<span id="example-responses-GETapi-consultations--consultation_id--prescription">
            <blockquote>
            <p>Example response (200, PDF (application/pdf)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">%PDF-1.7 ...</code>
 </pre>
            <blockquote>
            <p>Example response (404, Atendimento não encontrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Atendimento n&atilde;o encontrado.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Sem dados de refração):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O atendimento n&atilde;o tem dados de refra&ccedil;&atilde;o para gerar a receita.&quot;,
    &quot;errors&quot;: {
        &quot;eye_exam&quot;: [
            &quot;O atendimento n&atilde;o tem dados de refra&ccedil;&atilde;o para gerar a receita.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-consultations--consultation_id--prescription" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-consultations--consultation_id--prescription"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-consultations--consultation_id--prescription"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-consultations--consultation_id--prescription" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-consultations--consultation_id--prescription">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-consultations--consultation_id--prescription" data-method="GET"
      data-path="api/consultations/{consultation_id}/prescription"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-consultations--consultation_id--prescription', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-consultations--consultation_id--prescription"
                    onclick="tryItOut('GETapi-consultations--consultation_id--prescription');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-consultations--consultation_id--prescription"
                    onclick="cancelTryOut('GETapi-consultations--consultation_id--prescription');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-consultations--consultation_id--prescription"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/consultations/{consultation_id}/prescription</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-consultations--consultation_id--prescription"
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
                              name="Accept"                data-endpoint="GETapi-consultations--consultation_id--prescription"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>consultation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="consultation_id"                data-endpoint="GETapi-consultations--consultation_id--prescription"
               value="1"
               data-component="url">
    <br>
<p>ID do atendimento. Example: <code>1</code></p>
            </div>
                    </form>

                    <h2 id="atendimentos-GETapi-consultations--consultation_id--attachments">Listar anexos do atendimento</h2>

<p>
</p>

<p>Exames anexados ao atendimento (laudos, imagens), do mais recente para o mais antigo, sem paginação.
<code>404</code> se o atendimento não existir ou o paciente estiver na lixeira.</p>

<span id="example-requests-GETapi-consultations--consultation_id--attachments">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/consultations/1/attachments" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/consultations/1/attachments"
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

<span id="example-responses-GETapi-consultations--consultation_id--attachments">
            <blockquote>
            <p>Example response (200, Sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 3,
            &quot;consultation_id&quot;: 1,
            &quot;original_name&quot;: &quot;topografia.pdf&quot;,
            &quot;mime_type&quot;: &quot;application/pdf&quot;,
            &quot;size&quot;: 183245,
            &quot;description&quot;: &quot;Topografia de c&oacute;rnea&quot;,
            &quot;download_url&quot;: &quot;http://localhost:8000/api/attachments/3/download&quot;,
            &quot;created_at&quot;: &quot;2026-11-03T15:10:00-03:00&quot;
        }
    ]
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Atendimento não encontrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Atendimento n&atilde;o encontrado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-consultations--consultation_id--attachments" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-consultations--consultation_id--attachments"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-consultations--consultation_id--attachments"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-consultations--consultation_id--attachments" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-consultations--consultation_id--attachments">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-consultations--consultation_id--attachments" data-method="GET"
      data-path="api/consultations/{consultation_id}/attachments"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-consultations--consultation_id--attachments', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-consultations--consultation_id--attachments"
                    onclick="tryItOut('GETapi-consultations--consultation_id--attachments');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-consultations--consultation_id--attachments"
                    onclick="cancelTryOut('GETapi-consultations--consultation_id--attachments');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-consultations--consultation_id--attachments"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/consultations/{consultation_id}/attachments</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-consultations--consultation_id--attachments"
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
                              name="Accept"                data-endpoint="GETapi-consultations--consultation_id--attachments"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>consultation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="consultation_id"                data-endpoint="GETapi-consultations--consultation_id--attachments"
               value="1"
               data-component="url">
    <br>
<p>ID do atendimento. Example: <code>1</code></p>
            </div>
                    </form>

                    <h2 id="atendimentos-POSTapi-consultations--consultation_id--attachments">Anexar exame ao atendimento</h2>

<p>
</p>

<p>Envia um arquivo (multipart, campo <code>file</code>): PDF, JPEG ou PNG, até 10 MB. O tipo é conferido pelo conteúdo
(não só pela extensão). Vale para qualquer status de atendimento. O arquivo fica criptografado em disco
privado, com nome aleatório; o nome original é saneado (e recebe a extensão do tipo detectado).</p>

<span id="example-requests-POSTapi-consultations--consultation_id--attachments">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/consultations/1/attachments" \
    --header "Content-Type: multipart/form-data" \
    --header "Accept: application/json" \
    --form "description=Topografia de córnea"\
    --form "file=@/var/www/html/postman/fixtures/exam.pdf" </code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/consultations/1/attachments"
);

const headers = {
    "Content-Type": "multipart/form-data",
    "Accept": "application/json",
};

const body = new FormData();
body.append('description', 'Topografia de córnea');
body.append('file', document.querySelector('input[name="file"]').files[0]);

fetch(url, {
    method: "POST",
    headers,
    body,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-consultations--consultation_id--attachments">
            <blockquote>
            <p>Example response (201, Anexo criado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 3,
        &quot;consultation_id&quot;: 1,
        &quot;original_name&quot;: &quot;topografia.pdf&quot;,
        &quot;mime_type&quot;: &quot;application/pdf&quot;,
        &quot;size&quot;: 183245,
        &quot;description&quot;: &quot;Topografia de c&oacute;rnea&quot;,
        &quot;download_url&quot;: &quot;http://localhost:8000/api/attachments/3/download&quot;,
        &quot;created_at&quot;: &quot;2026-11-03T15:10:00-03:00&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Atendimento não encontrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Atendimento n&atilde;o encontrado.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Tipo não permitido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O arquivo deve ser um PDF, JPEG ou PNG.&quot;,
    &quot;errors&quot;: {
        &quot;file&quot;: [
            &quot;O arquivo deve ser um PDF, JPEG ou PNG.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-consultations--consultation_id--attachments" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-consultations--consultation_id--attachments"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-consultations--consultation_id--attachments"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-consultations--consultation_id--attachments" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-consultations--consultation_id--attachments">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-consultations--consultation_id--attachments" data-method="POST"
      data-path="api/consultations/{consultation_id}/attachments"
      data-authed="0"
      data-hasfiles="1"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-consultations--consultation_id--attachments', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-consultations--consultation_id--attachments"
                    onclick="tryItOut('POSTapi-consultations--consultation_id--attachments');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-consultations--consultation_id--attachments"
                    onclick="cancelTryOut('POSTapi-consultations--consultation_id--attachments');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-consultations--consultation_id--attachments"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/consultations/{consultation_id}/attachments</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-consultations--consultation_id--attachments"
               value="multipart/form-data"
               data-component="header">
    <br>
<p>Example: <code>multipart/form-data</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-consultations--consultation_id--attachments"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>consultation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="consultation_id"                data-endpoint="POSTapi-consultations--consultation_id--attachments"
               value="1"
               data-component="url">
    <br>
<p>ID do atendimento. Example: <code>1</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>file</code></b>&nbsp;&nbsp;
<small>file</small>&nbsp;
 &nbsp;
                <input type="file" style="display: none"
                              name="file"                data-endpoint="POSTapi-consultations--consultation_id--attachments"
               value=""
               data-component="body">
    <br>
<p>Arquivo PDF, JPEG ou PNG, até 10 MB. O tipo é conferido pelo conteúdo, não pela extensão. Must be a file. Must not be greater than 10240 kilobytes. Example: <code>postman/fixtures/exam.pdf</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="POSTapi-consultations--consultation_id--attachments"
               value="Topografia de córnea"
               data-component="body">
    <br>
<p>Descrição do anexo (opcional, até 255 caracteres). Must not be greater than 255 characters. Example: <code>Topografia de córnea</code></p>
        </div>
        </form>

                    <h2 id="atendimentos-GETapi-attachments--attachment_id--download">Baixar anexo</h2>

<p>
</p>

<p>O arquivo original (descriptografado), com o tipo detectado no envio e
<code>Content-Disposition: attachment; filename="&lt;nome saneado em ASCII&gt;"; filename*=UTF-8''&lt;nome saneado&gt;</code>.
O &quot;Try it out&quot; não exibe o arquivo; para baixar, abra a URL no navegador.</p>

<span id="example-requests-GETapi-attachments--attachment_id--download">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/attachments/1/download" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/attachments/1/download"
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

<span id="example-responses-GETapi-attachments--attachment_id--download">
            <blockquote>
            <p>Example response (200, Arquivo):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">&lt;bin&aacute;rio&gt;</code>
 </pre>
            <blockquote>
            <p>Example response (404, Anexo não encontrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Anexo n&atilde;o encontrado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-attachments--attachment_id--download" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-attachments--attachment_id--download"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-attachments--attachment_id--download"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-attachments--attachment_id--download" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-attachments--attachment_id--download">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-attachments--attachment_id--download" data-method="GET"
      data-path="api/attachments/{attachment_id}/download"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-attachments--attachment_id--download', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-attachments--attachment_id--download"
                    onclick="tryItOut('GETapi-attachments--attachment_id--download');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-attachments--attachment_id--download"
                    onclick="cancelTryOut('GETapi-attachments--attachment_id--download');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-attachments--attachment_id--download"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/attachments/{attachment_id}/download</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-attachments--attachment_id--download"
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
                              name="Accept"                data-endpoint="GETapi-attachments--attachment_id--download"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>attachment_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="attachment_id"                data-endpoint="GETapi-attachments--attachment_id--download"
               value="1"
               data-component="url">
    <br>
<p>ID do anexo. Example: <code>1</code></p>
            </div>
                    </form>

                    <h2 id="atendimentos-DELETEapi-attachments--id-">Excluir anexo</h2>

<p>
</p>

<p>Remove o anexo e o arquivo.</p>

<span id="example-requests-DELETEapi-attachments--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/attachments/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/attachments/1"
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

<span id="example-responses-DELETEapi-attachments--id-">
            <blockquote>
            <p>Example response (204, Anexo excluído):</p>
        </blockquote>
                <pre>
<code>Empty response</code>
 </pre>
            <blockquote>
            <p>Example response (404, Anexo não encontrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Anexo n&atilde;o encontrado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-attachments--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-attachments--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-attachments--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-attachments--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-attachments--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-attachments--id-" data-method="DELETE"
      data-path="api/attachments/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-attachments--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-attachments--id-"
                    onclick="tryItOut('DELETEapi-attachments--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-attachments--id-"
                    onclick="cancelTryOut('DELETEapi-attachments--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-attachments--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/attachments/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-attachments--id-"
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
                              name="Accept"                data-endpoint="DELETEapi-attachments--id-"
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
               step="any"               name="id"                data-endpoint="DELETEapi-attachments--id-"
               value="1"
               data-component="url">
    <br>
<p>The ID of the attachment. Example: <code>1</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>attachment_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="attachment_id"                data-endpoint="DELETEapi-attachments--id-"
               value="1"
               data-component="url">
    <br>
<p>ID do anexo. Example: <code>1</code></p>
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
