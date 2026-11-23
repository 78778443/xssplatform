import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { DynamicKeyEditor } from './dynamic-key-editor'

describe('DynamicKeyEditor', () => {
  it('adds and removes key names', () => {
    const onChange = vi.fn()
    const { rerender } = render(
      <DynamicKeyEditor label="接收参数" value={['cookie']} onChange={onChange} />,
    )
    expect(screen.getByText('cookie')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: /添加/i }))
    fireEvent.change(screen.getByLabelText(/新参数名/i), { target: { value: 'url' } })
    fireEvent.click(screen.getByRole('button', { name: /确定/i }))
    expect(onChange).toHaveBeenCalledWith(['cookie', 'url'])

    rerender(<DynamicKeyEditor label="接收参数" value={['cookie', 'url']} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: /删除 url/i }))
    expect(onChange).toHaveBeenLastCalledWith(['cookie'])
  })
})
