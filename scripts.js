const API_URL = "./crud.php";

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const msg = $("#msg");
const form = $("#form-contato");
const tbody = $("#tbody-contatos");
const btnSalvar = $("#btn-salvar");
const btnCancelar = $("#btn-cancelar");
const btnRecarregar = $("#btn-recarregar");

// Helper seguro para setar mensagens
function setMessage(text, type = "info") {
  msg.textContent = text || "";
  msg.classList.remove("ok", "err");
  if (type === "ok") msg.classList.add("ok");
  if (type === "err") msg.classList.add("err");
}

// Validação simples no frontend
function validateForm(data) {
  if (!data.nome || data.nome.trim().length < 2) {
    return "Informe um nome válido (mín. 2 caracteres).";
  }
  if (!data.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
    return "Informe um e-mail válido.";
  }
  // telefone é opcional; se vier, só checagem mínima
  if (data.telefone && data.telefone.length > 30) {
    return "Telefone muito longo.";
  }
  return null;
}

// Carregar dados (Read)
async function carregarContatos() {
  setMessage("Carregando...");
  try {
    const res = await fetch(`${API_URL}?action=read`, { method: "GET" });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || "Erro ao listar.");
    renderTabela(json.data || []);
    setMessage(`Carregado: ${json.data.length} registro(s).`, "ok");
  } catch (err) {
    console.error(err);
    setMessage(`Falha ao carregar: ${err.message}`, "err");
  }
}

// Renderizar tabela com segurança (textContent)
function renderTabela(items) {
  tbody.innerHTML = "";
  if (!items.length) {
    const tr = document.createElement("tr");
    const td = document.createElement("td");
    td.colSpan = 5;
    td.textContent = "Nenhum registro encontrado.";
    tr.appendChild(td);
    tbody.appendChild(tr);
    return;
  }
  for (const item of items) {
    const tr = document.createElement("tr");

    const tdId = document.createElement("td");
    tdId.textContent = item.id;

    const tdNome = document.createElement("td");
    tdNome.textContent = item.nome || "";

    const tdEmail = document.createElement("td");
    tdEmail.textContent = item.email || "";

    const tdTelefone = document.createElement("td");
    tdTelefone.textContent = item.telefone || "";

    const tdAcoes = document.createElement("td");
    tdAcoes.className = "actions-cell";

    const btnEditar = document.createElement("button");
    btnEditar.type = "button";
    btnEditar.textContent = "Editar";
    btnEditar.addEventListener("click", () => preencherFormulario(item));

    const btnExcluir = document.createElement("button");
    btnExcluir.type = "button";
    btnExcluir.textContent = "Excluir";
    btnExcluir.className = "btn-danger";
    btnExcluir.addEventListener("click", () => excluirContato(item.id));

    tdAcoes.appendChild(btnEditar);
    tdAcoes.appendChild(btnExcluir);

    tr.appendChild(tdId);
    tr.appendChild(tdNome);
    tr.appendChild(tdEmail);
    tr.appendChild(tdTelefone);
    tr.appendChild(tdAcoes);

    tbody.appendChild(tr);
  }
}

// Preencher formulário para atualização
function preencherFormulario(item) {
  $("#id").value = item.id;
  $("#nome").value = item.nome || "";
  $("#email").value = item.email || "";
  $("#telefone").value = item.telefone || "";
  btnSalvar.textContent = "Atualizar";
  setMessage(`Editando ID ${item.id}.`);
}

// Limpar formulário
function resetForm() {
  form.reset();
  $("#id").value = "";
  btnSalvar.textContent = "Salvar";
  setMessage("");
}

// Criar ou Atualizar
form.addEventListener("submit", async (e) => {
  e.preventDefault();
  const payload = {
    id: $("#id").value ? Number($("#id").value) : undefined,
    nome: $("#nome").value.trim(),
    email: $("#email").value.trim(),
    telefone: $("#telefone").value.trim(),
  };

  const validationError = validateForm(payload);
  if (validationError) {
    setMessage(validationError, "err");
    return;
  }

  const action = payload.id ? "update" : "create";

  try {
    setMessage(payload.id ? "Atualizando..." : "Salvando...");
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action, ...payload }),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || "Operação falhou.");
    setMessage(json.message || "Operação realizada com sucesso!", "ok");
    resetForm();
    await carregarContatos();
  } catch (err) {
    console.error(err);
    setMessage(err.message, "err");
  }
});

btnCancelar.addEventListener("click", resetForm);
btnRecarregar.addEventListener("click", carregarContatos);

// Exclusão
async function excluirContato(id) {
  if (!confirm(`Tem certeza que deseja excluir o ID ${id}?`)) return;
  try {
    setMessage(`Excluindo ID ${id}...`);
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "delete", id }),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || "Erro ao excluir.");
    setMessage(json.message || "Registro excluído.", "ok");
    await carregarContatos();
  } catch (err) {
    console.error(err);
    setMessage(err.message, "err");
  }
}

// Inicialização
window.addEventListener("DOMContentLoaded", carregarContatos);
