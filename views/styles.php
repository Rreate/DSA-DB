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

/* ---------- Updates ---------- */
/* Readable text in dropdowns, menu and dialogs (even in a dark-mode browser) */
#toolbar select { color: #1f2937; color-scheme: light; }
#toolbar select option { background: #fff; color: #1f2937; }
#ctx button { color: #1f2937; }
.dialog input[type=text], .dialog select, .dialog input[type=file] { color: #1f2937; background: #fff; }

/* Header: stats sit beside the folder, theme button on the right */
#header { justify-content: flex-start; gap: 12px; flex-wrap: wrap; }
#logo { stroke: #f0b400; }
#theme { margin-left: auto; padding: 7px 12px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff; color: #1f2937; cursor: pointer; font: inherit; font-size: 0.85rem; }

/* Filters on the left, round golden plus button first */
#toolbar { justify-content: flex-start; align-items: flex-end; }
#sidebar { order: -1; align-self: flex-end; margin-right: 6px; background: none; border: 0; padding: 0; min-width: 0; height: auto; box-shadow: none; }
#btn-pick { width: 46px; height: 46px; min-width: 0; padding: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f0b400; border-color: #f0b400; }
#btn-pick svg { display: block; stroke: #1f2937; }

/* Black theme (toggled by the button in the header) */
html[data-theme="black"] body { background: #000; color: #f5f5f5; }
html[data-theme="black"] #header, html[data-theme="black"] #file-manager, html[data-theme="black"] #toolbar, html[data-theme="black"] #ctx, html[data-theme="black"] .dialog { background: #000; border-color: #2a2a2a; color: #f5f5f5; }
html[data-theme="black"] #toolbar select, html[data-theme="black"] .dialog input[type=text], html[data-theme="black"] .dialog select, html[data-theme="black"] .dialog input[type=file] { background: #000; color: #f5f5f5; border-color: #444; color-scheme: dark; }
html[data-theme="black"] #toolbar select option { background: #000; color: #f5f5f5; }
html[data-theme="black"] #file-header { background: #1a1a1a; color: #fff; }
html[data-theme="black"] #file-list { border-color: #2a2a2a; }
html[data-theme="black"] .row { border-color: #1f1f1f; }
html[data-theme="black"] .row:hover { background: #141414; }
html[data-theme="black"] .row.picked { background: #262626; }
html[data-theme="black"] #ctx button { color: #f5f5f5; }
html[data-theme="black"] #ctx button:hover { background: #222; }
html[data-theme="black"] #ctx button.danger { color: #f87171; }
html[data-theme="black"] #stats, html[data-theme="black"] #toolbar label, html[data-theme="black"] .empty, html[data-theme="black"] .dialog p, html[data-theme="black"] .dialog label { color: #999; }
html[data-theme="black"] .buttons button, html[data-theme="black"] .buttons a { background: #111; border-color: #444; color: #f5f5f5; }
html[data-theme="black"] .buttons .ok { background: #f0b400; border-color: #f0b400; color: #1f2937; }
html[data-theme="black"] .buttons .bad { background: #dc2626; border-color: #dc2626; color: #fff; }
html[data-theme="black"] #theme { background: #000; color: #fff; border-color: #444; }

/* Login page */
#login-box { max-width: 360px; margin: 12vh auto 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.75rem; }
#login-box #logo { width: 34px; height: 34px; }
#login-box h1 { margin: .4rem 0 .1rem; font-size: 1.3rem; }
#login-box p { margin: 0 0 1rem; color: #6b7280; font-size: .9rem; }
#login-box label { display: block; font-size: .8rem; color: #6b7280; margin: .8rem 0 .25rem; }
#login-box input { width: 100%; box-sizing: border-box; padding: 9px 11px; border: 1px solid #d1d5db; border-radius: 8px; font: inherit; background: #fff; color: #1f2937; }
#login-box button { width: 100%; margin-top: 1.2rem; padding: 10px; border: 1px solid #2563eb; border-radius: 8px; background: #2563eb; color: #fff; font: inherit; cursor: pointer; }
#login-box .flash { margin-bottom: .5rem; }

/* Header: who is logged in */
#logout-form { display: flex; align-items: center; gap: 8px; margin: 0; }
#who { font-size: .85rem; color: #6b7280; }
#logout { padding: 7px 12px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff; color: #1f2937; cursor: pointer; font: inherit; font-size: .85rem; }

/* Pager */
#pager { display: flex; justify-content: center; flex-wrap: wrap; gap: 6px; padding: 14px 16px 20px; }
#pager .pg { min-width: 34px; padding: 6px 10px; text-align: center; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; color: #1f2937; text-decoration: none; font-size: .85rem; }
#pager a.pg:hover { background: #eff6ff; }
#pager .cur { background: #2563eb; border-color: #2563eb; color: #fff; }
#pager .off { color: #9ca3af; background: #f9fafb; }
#pager .gap { border: 0; background: none; min-width: 0; padding: 6px 2px; }

html[data-theme="black"] #login-box { background: #000; border-color: #2a2a2a; color: #f5f5f5; }
html[data-theme="black"] #login-box input { background: #000; color: #f5f5f5; border-color: #444; }
html[data-theme="black"] #login-box p, html[data-theme="black"] #login-box label, html[data-theme="black"] #who { color: #999; }
html[data-theme="black"] #logout, html[data-theme="black"] #pager .pg { background: #000; color: #fff; border-color: #444; }
html[data-theme="black"] #pager .cur { background: #f0b400; border-color: #f0b400; color: #1f2937; }
html[data-theme="black"] #pager .off { color: #666; }
</style>
