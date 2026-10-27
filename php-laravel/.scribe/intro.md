# Introduction

API REST para cadastro, consulta, edição e exclusão (soft delete) de pacientes (com endereço e foto), atendimentos, prontuário oftalmológico, receita de óculos e anexos de exames.

<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>

Esta documentação descreve a API REST do cadastro de pacientes.

Todas as respostas são JSON; envie o header `Accept: application/json`. CPF, RG e celular aceitam máscara
na entrada e são armazenados e retornados apenas com dígitos. Datas usam o formato `AAAA-MM-DD`.
A exclusão é lógica (soft delete): o paciente deixa de aparecer na listagem e passa a retornar 404.
CPF, RG e celular são armazenados criptografados no banco (a API sempre devolve os valores em claro); por isso
a busca por CPF exige o CPF completo. Cada alteração de paciente fica registrada no histórico (`/patients/{id}/audits`).

Atendimentos: datas e horas em ISO-8601, com fuso (`2026-11-03T14:30:00-03:00`) ou sem fuso (interpretado em
America/Sao_Paulo); as respostas sempre trazem o deslocamento de São Paulo. Os campos clínicos livres (`reason`,
`notes`, `diagnosis`, `observations`) são armazenados criptografados.
Endereço, foto e anexos: o paciente pode ter um endereço (`address`, logradouro/número/complemento/bairro
criptografados) e uma foto (`photo_url`); atendimentos podem ter anexos de exames (PDF, JPEG ou PNG). Fotos e anexos
ficam criptografados em disco privado. Uploads usam `multipart/form-data`. Toda resposta traz o header
`X-Request-Id` (o mesmo valor aparece na linha de log da requisição).

<aside>Os exemplos de requisição (bash e JavaScript) ficam na área escura à direita. Use o botão
"Try it out" de cada endpoint para enviar uma requisição real à API.</aside>

