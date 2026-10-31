<style>
* { box-sizing: border-box; }
body { margin: 0; font-family: system-ui, sans-serif; background: #f8f9fa; color: #1f2937; }

#header { display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 1.5rem; background: #fff; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 10; }
#header-nav { display: flex; align-items: center; gap: 1.5rem; }
#logo { width: 40px; height: 40px; }
#stats { font-size: 0.85rem; color: #6b7280; }

#main-container { display: flex; gap: 1rem; padding: 1rem; max-width: 1400px; margin: 0 auto; width: 100%; }
#file-manager { flex: 1; min-width: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; min-height: 30rem; }

/* Dropdown filters */
#toolbar { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; padding: 12px 16px; background: #fff; }
#toolbar label { display: flex; flex-direction: column; font-size: 0.75rem; color: #6b7280; gap: 3px; }
#toolbar select { padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; font-size: 0.9rem; min-width: 150px; }

/* The header and every row share the same columns */
.grid { display: grid; grid-template-columns: 60px minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 1.4fr) 90px 170px; gap: 10px; align-items: center; }
.grid > div { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#file-header { background: #d0e7f5; padding: 12px 16px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; }
#file-list { flex: 1; overflow: auto; border-top: 1px solid #e5e7eb; padding: 1px; }

/* Header buttons that look like plain header text */
.hbtn { color: inherit; text-decoration: none; cursor: pointer; display: inline-block; }
.hbtn::after { content: " \21C5"; opacity: 0.35; }
.hbtn.on::after { opacity: 1; }
.hbtn.asc::after { content: " \25B2"; }
.hbtn.desc::after { content: " \25BC"; }
.hbtn:hover { text-decoration: underline; }

.row { padding: 11px 16px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; cursor: context-menu; -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; }
.row:hover { background: #f5faff; }
.row.picked { background: #e0efff; }
.empty { padding: 3rem 1rem; text-align: center; color: #6b7280; }

#sidebar {display: flex; flex-direction: column; align-items: center; gap: 1rem; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 1rem; height: fit-content; min-width: 90px; }
#folder-icon { height: 48px; width: 48px; }
#btn-pick{ min-width: 10%; padding: 10px; border-radius: 60px; border: 1px solid #d1d5db; cursor: pointer; font-weight: 1000; font-size: 22px; background: #fff; font-family: inherit; 
background: #2563eb; color: #fff; border-color: #2563eb; }

#content-area { padding: 1rem 1rem 0; max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
.flash.error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.flash { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 10px 16px; border-radius: 8px; font-size: 0.9rem; }
.row:focus-visible { outline: 2px solid #2563eb; outline-offset: -2px; }
#ctx button:focus-visible { outline: 2px solid #2563eb; }

/* Right-click / hold menu */
#ctx { display: none; position: fixed; z-index: 50; background: #fff; border: 1px solid #d1d5db; border-radius: 8px; box-shadow: 0 6px 20px rgba(0,0,0,0.15); min-width: 170px; padding: 4px; }
#ctx button { display: block; width: 100%; text-align: left; padding: 10px 12px; border: 0; background: none; font-size: 0.95rem; cursor: pointer; border-radius: 6px; font-family: inherit; }
#ctx button:hover { background: #f3f4f6; }
#ctx button.danger { color: #b91c1c; }

/* Dialog overlay frame */
#overlay { display: none; position: fixed; inset: 0; z-index: 100; background: rgba(15,23,42,0.5); align-items: center; justify-content: center; padding: 1rem; }
.dialog { display: none; background: #fff; border-radius: 12px; padding: 1.2rem; width: 100%; max-width: 420px; max-height: 90vh; overflow: auto; }
.dialog h2 { margin: 0 0 0.8rem; font-size: 1.1rem; }
.dialog p { margin: 0 0 0.8rem; font-size: 0.9rem; color: #4b5563; word-break: break-word; }
.dialog label { display: block; font-size: 0.8rem; color: #6b7280; margin: 0.6rem 0 3px; }
.dialog input[type=text], .dialog select, .dialog input[type=file] { width: 100%; padding: 9px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.95rem; font-family: inherit; }
.buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 1rem; }
.buttons button, .buttons a { padding: 9px 16px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff; cursor: pointer; font-weight: 600; font-size: 0.9rem; text-decoration: none; color: inherit; font-family: inherit; }
.buttons .ok { background: #2563eb; border-color: #2563eb; color: #fff; }
.buttons .bad { background: #dc2626; border-color: #dc2626; color: #fff; }

@media (max-width: 900px) {
  #main-container { flex-direction: column-reverse; }
  #sidebar { flex-direction: row; min-width: 20%; justify-content: center; }
  .grid { grid-template-columns: 10px minmax(0, 1fr) 80px 50px; }
  .c-cat, .c-path { display: none; }
}
@media (max-width: 600px) {
  .grid { grid-template-columns: 40px minmax(0, 1fr) 80px; }
  .c-date { display: none; }
  #toolbar select { min-width: 120px; }
}
</style>
