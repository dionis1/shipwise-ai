import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FeedbackBox from './FeedbackBox.vue'
import PageControls from './PageControls.vue'
describe('accessible feedback and pagination', () => {
  it('announces errors without rendering HTML', () => {
    const wrapper = mount(FeedbackBox, { props: { error: '<script>alert(1)</script>' } })
    expect(wrapper.get('[role="alert"]').text()).toContain('<script>')
    expect(wrapper.find('script').exists()).toBe(false)
  })
  it('prevents navigating before the first page and beyond the last', () => {
    const wrapper = mount(PageControls, { props: { page: 1, total: 3, pageSize: 20 } })
    expect(
      wrapper.findAll('button').every((button) => button.attributes('disabled') !== undefined),
    ).toBe(true)
  })
  it('emits the requested page', async () => {
    const wrapper = mount(PageControls, { props: { page: 1, total: 21, pageSize: 20 } })
    await wrapper.findAll('button')[1].trigger('click')
    expect(wrapper.emitted('change')).toEqual([[2]])
  })
})
