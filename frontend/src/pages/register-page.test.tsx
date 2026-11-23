import { describe, it, expect, vi } from 'vitest'
import { screen, fireEvent, waitFor } from '@testing-library/react'
import { renderWithProviders } from '../test/render'
import { RegisterPage } from './register-page'
import * as apiModule from '../lib/api'

vi.mock('../lib/api', () => ({
  api: vi.fn(),
  ApiError: class ApiError extends Error {
    code = ''
    status = 400
  },
}))

describe('RegisterPage', () => {
  it('submits valid values without a phone field', async () => {
    const mocked = (apiModule.api as unknown as ReturnType<typeof vi.fn>)
    mocked.mockImplementation(async (action: string) => {
      if (action === 'register.check') {
        return { userExisted: 0, emailExisted: 0, keyError: 0 }
      }
      return { user: null, csrf: '' }
    })
    renderWithProviders(<RegisterPage />)
    expect(screen.queryByLabelText(/手机/i)).toBeNull()
    fireEvent.change(screen.getByLabelText(/用户名/i), { target: { value: 'newuser' } })
    fireEvent.change(screen.getByLabelText(/邮箱/i), { target: { value: 'new@example.com' } })
    fireEvent.change(screen.getByLabelText(/^密码$/), { target: { value: '123456' } })
    fireEvent.change(screen.getByLabelText(/确认密码/i), { target: { value: '123456' } })
    fireEvent.click(screen.getByRole('button', { name: /注册/i }))
    await waitFor(() => {
      expect(mocked).toHaveBeenCalledWith(
        'register.submit',
        expect.objectContaining({
          method: 'POST',
          body: expect.objectContaining({ user: 'newuser', email: 'new@example.com', pwd: '123456' }),
        }),
      )
    })
  })

  it('blocks submit when the passwords differ', async () => {
    const mocked = (apiModule.api as unknown as ReturnType<typeof vi.fn>)
    mocked.mockReset()
    mocked.mockResolvedValue({})
    renderWithProviders(<RegisterPage />)
    fireEvent.change(screen.getByLabelText(/用户名/i), { target: { value: 'newuser' } })
    fireEvent.change(screen.getByLabelText(/邮箱/i), { target: { value: 'new@example.com' } })
    fireEvent.change(screen.getByLabelText(/^密码$/), { target: { value: '123456' } })
    fireEvent.change(screen.getByLabelText(/确认密码/i), { target: { value: '654321' } })
    fireEvent.click(screen.getByRole('button', { name: /注册/i }))
    await waitFor(() => expect(screen.getByText(/两次输入密码不相同/i)).toBeTruthy())
    expect(mocked).not.toHaveBeenCalledWith('register.submit', expect.anything())
  })
})
