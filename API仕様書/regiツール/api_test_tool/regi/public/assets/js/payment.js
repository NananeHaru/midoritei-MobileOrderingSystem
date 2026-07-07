document.addEventListener("DOMContentLoaded", () => {
  const modal = document.getElementById("splitModal");
  const openBtn = document.getElementById("btnSplit");

  // ✅ 合計金額をPHPからJSへ

  // ===== モーダル開閉 =====
  const closeAll = () => modal.classList.remove("show");

  openBtn?.addEventListener("click", () => {
    modal.classList.add("show");
    setMode("equal");
    renderEqual();
  });

  // 右上× と キャンセル（data-close="split"）を全部拾う
  modal.querySelectorAll('[data-close="split"]').forEach(btn => {
    btn.addEventListener("click", closeAll);
  });

  // 背景クリックで閉じる
  modal.addEventListener("click", (e) => {
    if (e.target === modal) closeAll();
  });

  // ===== タブ（あなたのHTMLは data-smode を使ってる）=====
  const tabs = modal.querySelectorAll(".tab");
  const smode = document.getElementById("smode");

  function setMode(mode){
    // mode: equal / amount / item
    smode.value = mode;

    tabs.forEach(t => t.classList.remove("active"));
    // data-smode="tabEqual" みたいなやつを判定
    const map = { equal:"tabEqual", amount:"tabAmount", item:"tabItem" };
    modal.querySelector(`.tab[data-smode="${map[mode]}"]`)?.classList.add("active");

    // equal以外はとりあえずUIを差し替え（今は同じ表を出す）
    if(mode === "equal") renderEqual();
    if(mode === "amount") renderAmount();
    if(mode === "item") renderItem();
  }

  tabs.forEach(tab => {
    tab.addEventListener("click", () => {
      const v = tab.dataset.smode;
      if(v === "tabEqual") setMode("equal");
      if(v === "tabAmount") setMode("amount");
      if(v === "tabItem") setMode("item");
    });
  });

  // ===== 共通DOM =====
  const peopleInput = document.getElementById("speople");
  const tbody = document.getElementById("srows");
  const ssum = document.getElementById("ssum");
  const sdiff = document.getElementById("sdiff");
  const samounts = document.getElementById("samounts");
  const applyBtn = document.getElementById("sapply");

  const yen = (n)=> '¥' + Math.round(n).toLocaleString('ja-JP');

  // ===== 均等割 =====
  function renderEqual(){
    const p = Math.max(1, parseInt(peopleInput.value || "1", 10));
    const base = Math.floor(totalAmount / p);
    const rem  = totalAmount - base * p; // 端数

    tbody.innerHTML = "";
    const amounts = [];
    for(let i=1;i<=p;i++){
      const a = base + (i <= rem ? 1 : 0);
      amounts.push(a);
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>支払い ${i}</td>
        <td class="t-right">${yen(a)}</td>
      `;
      tbody.appendChild(tr);
    }
    updateSummary(amounts);
  }

  peopleInput?.addEventListener("input", () => {
    if(smode.value === "equal") renderEqual();
  });

  // ===== 金額指定 =====
  function renderAmount(){
    // 初期2行
    tbody.innerHTML = "";
    addAmountRow(1, "");
    addAmountRow(2, "");
    updateSummary(getAmountInputs());
  }

  function addAmountRow(idx, val){
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>支払い ${idx}</td>
      <td class="t-right">
        <input class="input s-amt" inputmode="numeric" placeholder="金額を入力" value="${val}"
               style="max-width:220px; text-align:right; display:inline-block;">
        <button type="button" class="icon-btn" style="margin-left:8px" title="削除">🗑</button>
      </td>
    `;
    tr.querySelector(".icon-btn").addEventListener("click", () => {
      tr.remove();
      renumberRows();
      updateSummary(getAmountInputs());
    });
    tr.querySelector(".s-amt").addEventListener("input", () => {
      updateSummary(getAmountInputs());
    });
    tbody.appendChild(tr);
  }

  function renumberRows(){
    [...tbody.querySelectorAll("tr")].forEach((tr,i)=>{
      tr.children[0].textContent = `支払い ${i+1}`;
    });
  }

  function getAmountInputs(){
    const arr = [];
    tbody.querySelectorAll(".s-amt").forEach(inp=>{
      const v = parseInt((inp.value||"0").replace(/[^0-9]/g,"") || "0", 10);
      arr.push(v);
    });
    return arr;
  }

  // ===== アイテム別（今は未実装表示） =====
  function renderItem(){
    tbody.innerHTML = `<tr><td colspan="2" style="padding:14px; color:#6b7a8a;">アイテム別の分割機能は開発中です</td></tr>`;
    updateSummary([]);
  }

  // ===== 合計・差分・確定可否 =====
  function updateSummary(amounts){
    const sum = amounts.reduce((a,b)=>a+b,0);
    const diff = totalAmount - sum;

    ssum.textContent  = yen(sum);
    sdiff.textContent = yen(diff);

    samounts.value = JSON.stringify(amounts);

    const ok = (diff === 0) && (amounts.length > 0) && (smode.value !== "item");
    applyBtn.disabled = !ok;
    applyBtn.classList.toggle("btn-disabled", !ok);
  }

  // 初期化（開いてない時は何もしない）
});