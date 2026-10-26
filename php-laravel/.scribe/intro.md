# Introduction

API REST para cadastro, consulta, edição e exclusão (soft delete) de pacientes.

<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>

Esta documentação descreve a API REST do cadastro de pacientes.

Todas as respostas são JSON; envie o header `Accept: application/json`. CPF, RG e celular aceitam máscara
na entrada e são armazenados e retornados apenas com dígitos. Datas usam o formato `AAAA-MM-DD`.
A exclusão é lógica (soft delete): o paciente deixa de aparecer na listagem e passa a retornar 404.

<aside>Os exemplos de requisição (bash e JavaScript) ficam na área escura à direita. Use o botão
"Try it out" de cada endpoint para enviar uma requisição real à API.</aside>

