import { config } from '@vue/test-utils'
import { i18n } from './i18n'
config.global.plugins = [i18n]

if (!HTMLDialogElement.prototype.showModal)
  HTMLDialogElement.prototype.showModal = function () {
    this.setAttribute('open', '')
  }
if (!HTMLDialogElement.prototype.close)
  HTMLDialogElement.prototype.close = function () {
    this.removeAttribute('open')
    this.dispatchEvent(new Event('close'))
  }
