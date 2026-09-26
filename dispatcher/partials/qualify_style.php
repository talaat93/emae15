<style>
.qual-grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 1rem; align-items: start; }
@media (max-width: 960px) { .qual-grid { grid-template-columns: 1fr; } }
.qual-chat { display: flex; flex-direction: column; }
.qual-msgs { padding: 1rem; display: flex; flex-direction: column; gap: .55rem; max-height: 62vh; overflow-y: auto; }
.qual-msg { max-width: 85%; padding: .6rem .85rem; border-radius: 12px; font-size: .92rem; line-height: 1.45; }
.qual-msg--ai { background: var(--d-card-2, #f3f5f9); border: 1px solid var(--d-border); align-self: flex-start; font-weight: 500; }
.qual-msg--me { background: var(--d-navy, #14264a); color: #fff; align-self: flex-end; }
.qual-answer { border-top: 1px solid var(--d-border); padding: .8rem 1rem 1rem; }
.qual-quick { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .6rem; }
.qual-chip { border: 1px solid var(--d-border-2); background: #fff; border-radius: 99px; padding: .4rem .85rem; font: inherit; font-size: .86rem; cursor: pointer; }
.qual-chip:hover { border-color: var(--d-orange); color: var(--d-orange); }
.qual-input { display: flex; gap: .5rem; align-items: stretch; }
.qual-input textarea { flex: 1; padding: .55rem .7rem; border: 1px solid var(--d-border-2); border-radius: 8px; font: inherit; resize: vertical; min-height: 44px; }
.qual-wait { margin-top: .5rem; font-size: .85rem; color: var(--d-t2); }
.qual-wait::before { content: ''; display: inline-block; width: .8rem; height: .8rem; margin-right: .4rem; vertical-align: -1px; border: 2px solid var(--d-border-2); border-top-color: var(--d-orange); border-radius: 50%; animation: qualspin .8s linear infinite; }
@keyframes qualspin { to { transform: rotate(360deg); } }
.qual-fields { display: grid; grid-template-columns: 1fr 1fr; gap: .45rem .7rem; }
.qual-field { display: flex; flex-direction: column; gap: .15rem; font-size: .78rem; color: var(--d-t2); }
.qual-field:has(textarea) { grid-column: 1 / -1; }
.qual-field input, .qual-field select, .qual-field textarea { padding: .35rem .5rem; border: 1px solid var(--d-border-2); border-radius: 6px; font: inherit; font-size: .86rem; color: var(--d-t1); background: #fff; width: 100%; }
.qual-field.is-missing input, .qual-field.is-missing select, .qual-field.is-missing textarea { border-color: #fca5a5; background: #fff7f7; }
.qual-field .req::after { content: ' *'; color: var(--d-danger); font-weight: 700; }
.qual-fields > div { grid-column: 1 / -1; }
.qual-danger { background: #dc2626; color: #fff; border-radius: 10px; padding: .9rem 1.1rem; margin-bottom: 1rem; box-shadow: 0 0 0 4px rgba(220,38,38,.2); }
.qual-danger-title { font-weight: 800; font-size: 1.02rem; margin-bottom: .35rem; }
.qual-danger ol { margin: 0; padding-left: 1.2rem; line-height: 1.55; }
.qual-known { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; border-radius: 8px; padding: .6rem .9rem; margin-bottom: 1rem; font-size: .9rem; }
.qual-known a { color: inherit; font-weight: 600; }
.qual-range { font-size: 1.05rem; }
.qual-actions { display: flex; justify-content: space-between; gap: .75rem; margin-top: 1rem; flex-wrap: wrap; }
.qual-pill { display: inline-block; padding: .1rem .5rem; border-radius: 99px; font-size: .72rem; font-weight: 700; }
.qual-pill--red { background: #fee2e2; color: #991b1b; }
@media (max-width: 600px) { .qual-fields { grid-template-columns: 1fr; } .qual-msg { max-width: 95%; } }
</style>
