import { describe, it, expect, vi } from 'vitest'
import { screen, fireEvent, waitFor } from '@testing-library/react'
import { renderWithProviders } from '../test/render'
import { ProjectConfigPage } from './project-config-page'
import * as apiModule from '../lib/api'

vi.mock('../lib/api', () => ({
  api: vi.fn(),
  ApiError: class ApiError extends Error {
    code = ''
    status = 400
  },
}))

describe('ProjectConfigPage', () => {
  it('prefills existing custom code and saves selection', async () => {
    const mocked = (apiModule.api as unknown as ReturnType<typeof vi.fn>)
    mocked.mockImplementation(async (action: string) => {
      if (action === 'project.setcode.get') {
        return {
          project: { id: 1, title: 'p', code: 'old-code' },
          modulesCan: [
            {
              id: 1,
              title: 'm',
              keys: 'cookie',
              code: 'module-code',
              setkeys: [{ key: 'keepsession', value: '1' }],
              choosed: 1,
            },
          ],
          ty: 'edit',
        }
      }
      return {}
    })
    renderWithProviders(<ProjectConfigPage id={1} />)
    await waitFor(() => expect(screen.getByDisplayValue('old-code')).toBeTruthy())
    fireEvent.click(screen.getByRole('button', { name: /配置/i }))
    await waitFor(() => {
      expect(mocked).toHaveBeenCalledWith(
        'project.setcode.save',
        expect.objectContaining({
          method: 'POST',
          body: expect.objectContaining({ id: 1, code: 'old-code' }),
        }),
      )
    })
  })
})
