# Admin panel design conventions

The user explicitly selected these existing views as the design references for all future admin pages:
- `resources/views/backend/user/list.blade.php`
- `resources/views/backend/user/edit.blade.php`
- `resources/views/backend/banners/list.blade.php`
- `resources/views/backend/banners/edit.blade.php`

Read the relevant reference before creating or redesigning an admin page. Reuse the existing Acorn/Bootstrap theme and components; do not introduce a separate visual style.

## Shared layout
- Extend `backend.layout` with `$html_tag_data`, `$title`, and `$breadcrumbs`.
- Use `.container`, `.page-title-container > .row`, title column `.col-12.col-sm-6`, `h1.mb-0.pb-0.display-4#title`, and `backend._layout.breadcrumb`.
- Put page actions in `.col-12.col-sm-6.d-flex.align-items-start.justify-content-end`.
- Admin text is Azerbaijani; use `name_az` where applicable.

## List pages
- Use `.scroll-section`, `.card.mb-5`, `.card-body` and the reference DataTable layout.
- Include DataTables CSS, then DataTables JS, scrollspy.js, datatable.extend.js and datatable.boxedvariations.js in the layout sections.
- Use `.data-table.data-table-pagination.data-table-standard.responsive.nowrap.hover#datatableHover`.
- Reuse the compact search, export dropdown and page-size controls, all pointing to the matching table ID through `data-datatable`.
- Headers use `.text-muted.text-small.text-uppercase`; secondary cells `.text-alternate`; edit links `.btn.btn-primary.btn-sm`.
- The top-right create button uses `.btn.btn-outline-primary.btn-icon.btn-icon-end.w-100.w-sm-auto` and an Acorn plus icon.
- Create forms open in a `.modal.modal-right.fade` with standard modal header/body and vertically arranged labeled controls. Preserve `old()` input, show validation errors and reopen the modal after failed creation, as in banners/list.

## Edit pages
- Use a separate edit page, `.scroll-section`, optional `.small-title`, `.card.h-100-card > .card-body`.
- Form rows: `.mb-3.row`; labels `.col-lg-2.col-md-3.col-sm-4.col-form-label`; controls inside `.col-sm-8.col-md-9.col-lg-10`.
- Inputs use `.form-control`, selects `.form-select`; show field errors and preserve old values.
- Submit row: `.mb-3.row.mt-5`, with `.col-sm-8.col-md-9.col-lg-10.ms-auto` and primary “Yenilə” button.
- Keep CSRF, authorization, and route/method requirements intact when adapting these visual references.
