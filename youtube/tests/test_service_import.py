import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import MagicMock, patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import service_import


class ServiceImportTests(unittest.TestCase):
    def run_channel(self, tab_state):
        with tempfile.TemporaryDirectory() as folder:
            backend = MagicMock()
            backend.extract.return_value = (None, {'channel_id': 'UCfixture'}, [])
            with patch.object(sys, 'argv', ['service_import.py', '--url', 'https://www.youtube.com/@fixture', '--output', folder, '--node', 'node']), \
                    patch.object(service_import, 'YouTube', return_value=backend), \
                    patch.object(service_import, 'Lock'), \
                    patch.object(service_import, 'inventory', return_value={'entries': [], 'tabs': {'videos': {'state': tab_state}}}), \
                    patch.object(service_import, 'collect_posts', return_value={'state': 'complete'}):
                return service_import.main()

    def test_failed_inventory_tab_is_not_reported_complete(self):
        self.assertEqual(2, self.run_channel('failed'))

    def test_complete_inventory_and_posts_can_finish(self):
        self.assertEqual(0, self.run_channel('complete'))


if __name__ == '__main__':
    unittest.main()
