import { describe, it, expect, vi } from 'vitest'
import { screen, fireEvent, waitFor } from '@testing-library/react'
import { renderWithProviders } from '../test/render'
import { SettingsPage } from './settings-page'
import * as apiModule from '../lib/api'

vi.mock('../lib/api', () => ({
  api: vi.fn(),
  ApiError: class ApiError extends Error {
    code = ''
    status = 400
  },
}))

describe('SettingsPage', () => {
  it('saves dingding key and two toggles without phone fields', async () => {
    const mocked = (apiModule.api as unknown as ReturnType<typeof vi.fn>)
    mocked.mockImplementation(async (action: string) => {
      if (action === 'user.settings.get') {
        return { email: 'a@b.c', dingding: 'k', emsg: '0', dmsg: '0' }
      }
      return {}
    })
    renderWithProviders(<SettingsPage />)
    await waitFor(() => expect(screen.getByDisplayValue('k')).toBeTruthy())
    expect(screen.queryByLabelText(/手机/i)).toBeNull()
    expect(screen.queryByText(/短信/i)).toBeNull()
    fireEvent.click(screen.getAllByRole('switch')[0])
    fireEvent.click(screen.getAllByRole('switch')[1])
    fireEvent.change(screen.getByLabelText(/钉钉机器人Key/i), { target: { value: 'new-key' } })
    fireEvent.click(screen.getByRole('button', { name: /提交/i }))
    await waitFor(() => {
      expect(mocked).toHaveBeenCalledWith(
        'user.settings.save',
        expect.objectContaining({
          method: 'POST',
          body: expect.objectContaining({ dingding: 'new-key', emsg: '1', dmsg: '1' }),
        }),
      )
    })
  })
})
