import { readFileSync, readdirSync } from 'node:fs'
import { fileURLToPath, URL } from 'node:url'
import { resolve, relative } from 'node:path'
import process from 'node:process'
import { parse } from 'vue/compiler-sfc'
const root = fileURLToPath(new URL('../', import.meta.url))
const messages = Object.fromEntries(
  ['en', 'de', 'fr'].map((locale) => [
    locale,
    JSON.parse(readFileSync(resolve(root, 'src/i18n/locales/' + locale + '.json'), 'utf8')),
  ]),
)
function flatten(value, prefix = '') {
  return Object.fromEntries(
    Object.entries(value).flatMap(([key, item]) =>
      typeof item === 'object'
        ? Object.entries(flatten(item, prefix + key + '.'))
        : [[prefix + key, item]],
    ),
  )
}
const baseline = flatten(messages.en),
  failures = []
for (const [locale, copy] of Object.entries(messages)) {
  const entries = flatten(copy)
  for (const key of new Set([...Object.keys(baseline), ...Object.keys(entries)])) {
    if (typeof entries[key] !== 'string' || !entries[key].trim())
      failures.push(locale + ': missing or empty ' + key)
    else if (!(key in baseline)) failures.push(locale + ': unexpected ' + key)
    else if (
      JSON.stringify((entries[key].match(/\{[^}]+\}/g) || []).sort()) !==
      JSON.stringify((baseline[key].match(/\{[^}]+\}/g) || []).sort())
    )
      failures.push(locale + ': mismatched placeholders ' + key)
  }
}
function files(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) =>
    entry.isDirectory() ? files(resolve(directory, entry.name)) : [resolve(directory, entry.name)],
  )
}
function hasCopy(value) {
  return /\p{L}/u.test(value.replaceAll('ShipWise', '').replaceAll('AI', ''))
}
for (const file of files(resolve(root, 'src')).filter((file) => file.endsWith('.vue'))) {
  const ast = parse(readFileSync(file, 'utf8')).descriptor.template?.ast
  function visit(node) {
    if (node.type === 2 && hasCopy(node.content))
      failures.push(relative(root, file) + ': untranslated text ' + node.content.trim())
    for (const prop of node.props || [])
      if (
        prop.type === 6 &&
        ['placeholder', 'aria-label', 'alt', 'title'].includes(prop.name) &&
        prop.value &&
        hasCopy(prop.value.content)
      )
        failures.push(relative(root, file) + ': untranslated ' + prop.name)
    for (const child of node.children || []) visit(child)
  }
  if (ast) visit(ast)
}
if (failures.length) {
  process.stderr.write(failures.join('\n') + '\n')
  process.exitCode = 1
} else
  process.stdout.write(
    `Translation coverage complete: ${Object.keys(baseline).length} keys per locale; placeholders match; Vue copy is localized.\n`,
  )
