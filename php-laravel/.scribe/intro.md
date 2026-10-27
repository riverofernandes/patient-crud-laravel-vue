# Introduction

API REST para cadastro, consulta, edição e exclusão (soft delete) de pacientes, atendimentos, prontuário oftalmológico e receita de óculos.

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

<aside>Os exemplos de requisição (bash e JavaScript) ficam na área escura à direita. Use o botão
"Try it out" de cada endpoint para enviar uma requisição real à API.</aside>

